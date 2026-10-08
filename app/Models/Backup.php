<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

#[Fillable(['filename', 'size', 'status', 'drive_status', 'drive_file_id', 'error', 'trigger'])]
class Backup extends Model
{
    public const DIRECTORY = 'backups';

    public function path(): string
    {
        return self::DIRECTORY.'/'.$this->filename;
    }

    public function sizeLabel(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
