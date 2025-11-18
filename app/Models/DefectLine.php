<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefectLine extends Model
{
  protected $fillable = [
    'defect_id','batch_id','defect_type_id','subtype_id','qty_pcs','qty_kg'
  ];

  public function defect()
  {
    return $this->belongsTo(Defect::class);
  }

  public function batch()
  {
    return $this->belongsTo(Batch::class);
  }

  public function type()
  {
    return $this->belongsTo(DefectType::class, 'defect_type_id');
  }

  public function subtype()
  {
    return $this->belongsTo(DefectType::class, 'subtype_id');
  }

  // --- ALIAS untuk kompatibilitas (memperbaiki error "defectType" undefined)
  public function defectType()
  {
    return $this->type();
  }

  // Jika ada kode lama memanggil defectSubtype, tambahkan juga
  public function defectSubtype()
  {
    return $this->subtype();
  }
}
