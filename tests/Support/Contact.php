<?php

declare(strict_types=1);

namespace Chikolokoy08\PhToolkit\Tests\Support;

use Chikolokoy08\PhToolkit\Laravel\Casts\AsPhMobileNumber;
use Chikolokoy08\PhToolkit\Laravel\Casts\AsPhTin;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $mobile
 * @property string|null $tin
 */
final class Contact extends Model
{
    protected $table = 'contacts';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'mobile' => AsPhMobileNumber::class,
            'tin' => AsPhTin::class,
        ];
    }
}
