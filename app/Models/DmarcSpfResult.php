<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DmarcSpfResult extends Model
{
    protected $fillable = [
        'domain',
        'result',
    ];

    /**
     * Define an inverse one-to-many relationship with the DmarcRecord model.
     *
     * @return BelongsTo<DmarcRecord, $this>
     */
    public function dmarcRecord(): BelongsTo
    {
        return $this->belongsTo(DmarcRecord::class);
    }
}
