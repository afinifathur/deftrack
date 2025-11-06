<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Defect extends Model {
  protected $fillable=['date','department_id','status','submitted_by','approved_by','rejected_reason'];
  protected $casts=['date'=>'date'];
  public function department(){return $this->belongsTo(Department::class);}
  public function lines(){return $this->hasMany(DefectLine::class);}
}