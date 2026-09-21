import { readFileSync } from "node:fs";
import { JSDOM } from "jsdom";

const ROOT = new URL("../../", import.meta.url);
const TEMPLATE = new URL("resources/views/components/address-selector.blade.php", ROOT);

export const fixtures = JSON.parse(readFileSync(new URL("./fixtures.json", import.meta.url), "utf8"));

/**
 * The script under test is read out of the Blade template, so the tests run
 * whatever the component actually ships rather than a copy of it.
 */
export function componentScript() {
  const blade = readFileSync(TEMPLATE, "utf8");
  const match = blade.match(/<script>([\s\S]*?)<\/script>/);

  if (match === null) {
    throw new Error("No <script> block in " + TEMPLATE.pathname);
  }

  return match[1];
}

const LEVELS = ["region", "province", "city", "barangay"];

/** The markup the Blade template renders, with the attributes the script reads. */
function markup({ endpoint, withSub, preset }) {
  const data = LEVELS.map((level) => `data-${level}="${preset[level] ?? ""}"`).join(" ");

  return `<div data-ph-address-selector data-endpoint="${endpoint}"
      data-include-sub-municipalities="${withSub ? "1" : "0"}" ${data}>
    ${LEVELS.map(
      // City and barangay start disabled in the template, because there is
      // nothing to choose from until the level above is picked.
      (level) => `<div ${level === "province" ? 'data-level-field="province" hidden' : ""}>
        <label for="f-${level}">${level}</label>
        <select id="f-${level}" name="address[${level}_code]" data-level="${level}"
          ${level === "city" || level === "barangay" ? "disabled" : ""}>
          <option value=""></option>
        </select>
      </div>`,
    ).join("")}
  </div>`;
}

export async function mount({ endpoint = "/ph-toolkit", withSub = false, preset = {} } = {}) {
  const requested = [];
  const dom = new JSDOM(`<!doctype html><body>${markup({ endpoint, withSub, preset })}</body>`, {
    runScripts: "outside-only",
    url: "https://example.test/",
  });

  dom.window.fetch = async (url) => {
    requested.push(url);
    const body = fixtures[url];

    return body === undefined
      ? { ok: false, status: 404, json: async () => ({}) }
      : { ok: true, status: 200, json: async () => structuredClone(body) };
  };

  dom.window.eval(componentScript());
  await settle(dom);

  const root = dom.window.document.querySelector("[data-ph-address-selector]");
  const selects = Object.fromEntries(
    LEVELS.map((level) => [level, root.querySelector(`[data-level="${level}"]`)]),
  );

  return {
    dom,
    root,
    selects,
    requested,
    provinceField: root.querySelector('[data-level-field="province"]'),
    labels: (level) => [...selects[level].options].slice(1).map((o) => o.textContent),
    values: (level) => [...selects[level].options].slice(1).map((o) => o.value),
    async choose(level, value) {
      selects[level].value = value;
      selects[level].dispatchEvent(new dom.window.Event("change"));
      await settle(dom);
    },
  };
}

export async function settle(dom) {
  for (let i = 0; i < 25; i += 1) {
    await new Promise((resolve) => setImmediate(resolve));
  }
}
