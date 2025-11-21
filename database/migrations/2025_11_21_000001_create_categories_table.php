<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up()
{
Schema::create('categories', function (Blueprint $table) {
$table->id();
$table->string('name')->unique();
$table->string('tag')->nullable(); // optional tag/label
$table->timestamps();
$table->softDeletes();
});


Schema::create('category_department', function (Blueprint $table) {
$table->unsignedBigInteger('category_id');
$table->unsignedBigInteger('department_id');
$table->primary(['category_id','department_id']);


$table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
$table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
});
}


public function down()
{
Schema::dropIfExists('category_department');
Schema::dropIfExists('categories');
}
};