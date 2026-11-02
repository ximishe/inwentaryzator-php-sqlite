<?php
namespace App\Models;

class Model {
    public $db;

    public function __construct() {
        require_once __DIR__ . '/../../inc/db.php';
        $this->db = db();
    }
}
