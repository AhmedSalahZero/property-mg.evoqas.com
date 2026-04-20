<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = [
        'company_id',
        'name',
    ];

    protected static function booted(): void
    {
        static::saving(function (Tag $tag): void {
            $trimmed = Str::limit(trim((string) $tag->name), 150, '');
            $tag->name = $trimmed;
            $tag->normalized_name = Str::lower($trimmed);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_tag');
    }

    /**
     * Find an existing tag for the company by case-insensitive name, or create a new one.
     */
    public static function findOrCreateForCompany(int $companyId, string $rawName): self
    {
        $name = Str::limit(trim($rawName), 150, '');
        if ($name === '') {
            throw new \InvalidArgumentException('Tag name cannot be empty.');
        }

        $normalized = Str::lower($name);

        return static::firstOrCreate(
            [
                'company_id' => $companyId,
                'normalized_name' => $normalized,
            ],
            [
                'name' => $name,
            ]
        );
    }
}
