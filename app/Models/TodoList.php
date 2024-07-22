<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Task;

class TodoList extends Model
{
    use HasFactory;
	protected $table = 'lists';
	protected $fillable = ['user_id', 'name', 'background'];

	public function tasks()
	{
		return $this->hasMany(Task::class, 'list_id');
	}
}
