<?php

namespace Source\Models\cafeApp;

class appCategory extends Model
{
    public function __construct()
    {
        parent::__construct("app_catregories", ["id"], ["name", "type"]);
    }
}