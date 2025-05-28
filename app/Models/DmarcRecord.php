<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DmarcRecord extends Model
{
    protected $fillable = [
        'source_ip',
        'count',
        'disposition',
        'dkim_result',
        'spf_result',
    ];

    /**
     * Define an inverse one-to-many relationship with the DmarcReport model.
     *
     * @return BelongsTo<DmarcReport, $this>
     */
    public function dmarcReport(): BelongsTo
    {
        return $this->belongsTo(DmarcReport::class);
    }

    /**
     * Define a one-to-many relationship with the DmarcDkimResult model.
     *
     * @return HasMany<DmarcDkimResult, $this>
     */
    public function dmarcDkimResults(): HasMany
    {
        return $this->hasMany(DmarcDkimResult::class);
    }

    /**
     * Define a one-to-many relationship with the DmarcSpfResult model.
     *
     * @return HasMany<DmarcSpfResult, $this>
     */
    public function dmarcSpfResults(): HasMany
    {
        return $this->hasMany(DmarcSpfResult::class);
    }
}
