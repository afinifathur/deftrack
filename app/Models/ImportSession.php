<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportSession extends Model {
  protected $fillable=['date','department_id','created_by','note'];
  protected $casts=['date'=>'date'];
  public function department(){return $this->belongsTo(Department::class);}
  public function batches(){return $this->hasMany(Batch::class);}
}