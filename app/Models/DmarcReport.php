<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DmarcReport extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider_name',
        'provider_email',
        'provider_extra_contact',
        'report_start',
        'report_end',
        'dkim_alignment',
        'spf_alignment',
        'policy',
        'sub_domain_policy',
        'percentage',
        'domain',
        'report_id',
    ];

    /**
     * Define an inverse one-to-many relationship with the User model.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define a one-to-many relationship with the DmarcRecord model.
     *
     * @return HasMany<DmarcRecord, $this>
     */
    public function dmarcRecords(): HasMany
    {
        return $this->hasMany(DmarcRecord::class);
    }
}
