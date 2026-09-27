<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A normalized, geocoded place — shared across birth/death locations and
 * story locations so the same city or address is stored once and can be
 * plotted on the map.
 *
 * @property int $id
 * @property string $formatted_address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $precision
 * @property string|null $city
 * @property string|null $region
 * @property string|null $country
 * @property string|null $country_code
 * @property string $source
 * @property string|null $external_ref
 */
#[Fillable([
    'formatted_address', 'latitude', 'longitude', 'precision', 'city', 'region', 'country', 'country_code', 'source', 'external_ref',
])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * A short "City, Region" style label for inline display (e.g. "Born in
     * Portland, OR") — falls back down to whatever's available, ending at
     * the full formatted address for results with no parsed city/region.
     */
    public function shortLabel(): string
    {
        $parts = array_filter([$this->city, $this->region ?: $this->country]);

        return $parts !== [] ? implode(', ', $parts) : $this->formatted_address;
    }
}
