import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { componentScript, mount } from "./selector.mjs";

describe("the region step", () => {
  it("loads the regions on mount", async () => {
    const page = await mount();

    assert.deepEqual(page.labels("region"), [
      "Region VII (Central Visayas)",
      "National Capital Region (NCR)",
    ]);
    assert.equal(page.selects.region.disabled, false);
  });

  it("leaves the levels below it empty and disabled", async () => {
    const page = await mount();

    assert.deepEqual(page.labels("city"), []);
    assert.deepEqual(page.labels("barangay"), []);
    assert.equal(page.selects.city.disabled, true);
    assert.equal(page.selects.barangay.disabled, true);
  });
});

describe("a region with no provinces", () => {
  it("hides the province step", async () => {
    const page = await mount();
    await page.choose("region", "1300000000");

    assert.equal(page.provinceField.hidden, true);
    assert.deepEqual(page.labels("province"), []);
  });

  it("lists the region's cities", async () => {
    const page = await mount();
    await page.choose("region", "1300000000");

    assert.deepEqual(page.labels("city"), ["City of Manila", "City of Parañaque"]);
  });

  it("asks for districts only when told to", async () => {
    const plain = await mount();
    await plain.choose("region", "1300000000");
    assert.ok(!plain.labels("city").includes("Tondo I/II"));

    const withSub = await mount({ withSub: true });
    await withSub.choose("region", "1300000000");
    assert.ok(withSub.labels("city").includes("Tondo I/II"));
  });
});

describe("a region with provinces and independent cities", () => {
  it("shows the province step", async () => {
    const page = await mount();
    await page.choose("region", "0700000000");

    assert.equal(page.provinceField.hidden, false);
    assert.deepEqual(page.labels("province"), ["Bohol", "Cebu"]);
  });

  it("lists only the province-less cities before a province is chosen", async () => {
    const page = await mount();
    await page.choose("region", "0700000000");

    // This is the case a naive cascade gets wrong: the highly urbanized cities
    // belong to no province and would otherwise be unreachable.
    assert.deepEqual(page.labels("city"), ["City of Cebu", "City of Mandaue"]);
  });

  it("lists the province's cities once one is chosen", async () => {
    const page = await mount();
    await page.choose("region", "0700000000");
    await page.choose("province", "0702200000");

    assert.deepEqual(page.labels("city"), ["Argao", "Asturias"]);
  });
});

describe("the barangay step", () => {
  it("loads the barangays of the chosen city", async () => {
    const page = await mount();
    await page.choose("region", "0700000000");
    await page.choose("city", "0730600000");

    assert.deepEqual(page.labels("barangay"), ["Adlaon", "Lahug"]);
    assert.equal(page.selects.barangay.disabled, false);
  });

  it("takes the City of Manila's barangays from its districts", async () => {
    const page = await mount();
    await page.choose("region", "1300000000");
    await page.choose("city", "1380600000");

    assert.deepEqual(page.labels("barangay"), ["Barangay 1", "Barangay 2"]);
  });
});

describe("resetting the levels below a change", () => {
  it("clears the city and barangay when the region changes", async () => {
    const page = await mount();
    await page.choose("region", "0700000000");
    await page.choose("province", "0702200000");
    await page.choose("city", "0702201000");
    assert.deepEqual(page.labels("barangay"), ["Poblacion"]);

    await page.choose("region", "1300000000");

    assert.deepEqual(page.labels("barangay"), []);
    assert.equal(page.selects.barangay.value, "");
    assert.equal(page.selects.province.value, "");
  });

  it("clears the barangay when the province changes", async () => {
    const page = await mount();
    await page.choose("region", "0700000000");
    await page.choose("city", "0730600000");
    assert.equal(page.labels("barangay").length, 2);

    await page.choose("province", "0702200000");

    assert.deepEqual(page.labels("barangay"), []);
  });
});

describe("an edit form", () => {
  it("rebuilds the chain from preselected codes", async () => {
    const page = await mount({
      preset: {
        region: "0700000000",
        province: "0702200000",
        city: "0702201000",
        barangay: "0702201001",
      },
    });

    assert.equal(page.selects.region.value, "0700000000");
    assert.equal(page.selects.province.value, "0702200000");
    assert.equal(page.selects.city.value, "0702201000");
    assert.equal(page.selects.barangay.value, "0702201001");
  });

  it("handles an independent city that has no province", async () => {
    const page = await mount({ preset: { region: "0700000000", city: "0730600000" } });

    assert.equal(page.selects.province.value, "");
    assert.equal(page.selects.city.value, "0730600000");
    assert.deepEqual(page.labels("barangay"), ["Adlaon", "Lahug"]);
  });

  it("ignores a code that is not in the list it belongs to", async () => {
    const page = await mount({ preset: { region: "0700000000", city: "9999999999" } });

    assert.equal(page.selects.city.value, "");
    assert.deepEqual(page.labels("barangay"), []);
  });
});

describe("how it talks to the endpoints", () => {
  it("asks only for what it needs", async () => {
    const page = await mount();

    assert.deepEqual(page.requested, ["/ph-toolkit/regions"]);

    await page.choose("region", "1300000000");

    assert.deepEqual(page.requested, [
      "/ph-toolkit/regions",
      "/ph-toolkit/provinces?region=1300000000",
      "/ph-toolkit/cities?region=1300000000",
    ]);
  });

  it("never asks for every barangay at once", async () => {
    const page = await mount({ preset: { region: "0700000000", city: "0730600000" } });
    await page.choose("region", "1300000000");

    assert.ok(page.requested.every((url) => !/\/barangays(\?|$)(?!.*city=)/.test(url)));
  });

  it("uses the configured endpoint prefix", async () => {
    const page = await mount({ endpoint: "/api/psgc" });

    // The fixtures are keyed on the default prefix, so nothing resolves here.
    assert.deepEqual(page.requested, ["/api/psgc/regions"]);
    assert.deepEqual(page.labels("region"), []);
  });

  it("survives an endpoint that fails", async () => {
    const page = await mount({ endpoint: "/missing" });

    assert.equal(page.root.dataset.phAddressSelectorError, "1");
  });
});

describe("the markup it produces", () => {
  it("puts place names in as text, never as markup", async () => {
    const page = await mount();
    await page.choose("region", "1300000000");

    const option = [...page.selects.city.options].find((o) => o.value === "1381000000");

    assert.equal(option.textContent, "City of Parañaque");
    assert.equal(option.children.length, 0);
  });

  it("escapes a name that contains markup", async () => {
    const page = await mount();
    await page.choose("region", "1300000000");

    const select = page.selects.city;
    const injected = page.dom.window.document.createElement("option");
    injected.textContent = '<img src=x onerror="alert(1)">';
    select.appendChild(injected);

    // textContent is what the component uses, and it does not parse markup.
    assert.equal(injected.children.length, 0);
    assert.ok(select.innerHTML.includes("&lt;img"));
  });

  it("exposes an initializer for content added after load", () => {
    assert.ok(componentScript().includes("window.PhAddressSelector"));
  });

  it("brings in no external script", () => {
    assert.ok(!componentScript().includes("import "));
    assert.ok(!componentScript().includes("require("));
  });
});
