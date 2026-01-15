<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trace extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pv_id',
        'action',
        'ip_address',
        'user_agent',
        'donnees_pv',
        'metadata',
    ];

    protected $casts = [
        'donnees_pv' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pv(): BelongsTo
    {
        return $this->belongsTo(ProcesVerbal::class, 'pv_id');
    }

    // Méthode helper pour formater les données
    public function getFormattedMetadata(): array
    {
        $meta = $this->metadata ?? [];
        
        return [
            'inscrits' => $meta['inscrits'] ?? 0,
            'votants' => $meta['votants'] ?? 0,
            'nuls' => $meta['nuls'] ?? 0,
            'exprimes' => $meta['exprimes'] ?? 0,
            'taux_participation' => $meta['inscrits'] > 0 
                ? round(($meta['votants'] / $meta['inscrits']) * 100, 2) 
                : 0,
            'taux_nuls' => $meta['votants'] > 0 
                ? round(($meta['nuls'] / $meta['votants']) * 100, 2) 
                : 0,
        ];
    }
}