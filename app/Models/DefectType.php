<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DefectType extends Model {
  protected $fillable=['name','parent_id','is_active'];
  protected $casts=['is_active'=>'boolean'];
  public function parent(){return $this->belongsTo(DefectType::class,'parent_id');}
  public function children(){return $this->hasMany(DefectType::class,'parent_id');}
}