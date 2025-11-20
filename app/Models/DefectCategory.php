<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DefectCategory extends Model {
    protected $fillable=['name','slug','description'];
    public function variants(){ return $this->hasMany(DefectTypeVariant::class,'defect_category_id'); }
}
