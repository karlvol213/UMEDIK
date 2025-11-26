<?php
require_once __DIR__ . '/Model.php';

class Service extends Model
{
    private $id;
    private $name;
    private $description;
    private $duration;
    private $active;
    private $created_at;

    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->duration = $data['duration'] ?? 30;
        $this->active = isset($data['active']) ? (bool)$data['active'] : true;
        $this->created_at = $data['created_at'] ?? null;
    }

    public static function getAll()
    {
        $pdo = self::pdo();
        $stmt = $pdo->query('SELECT * FROM services WHERE active = 1 ORDER BY name');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[] = new self($r);
        return $out;
    }
}

?>
