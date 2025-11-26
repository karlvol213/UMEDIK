<?php
require_once __DIR__ . '/Model.php';

class HistoryLog extends Model
{
    private $id;
    private $user_id;
    private $action;
    private $description;
    private $created_at;

    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
        $this->action = $data['action'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
    }

    public function create()
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('INSERT INTO history_logs (user_id,action,description) VALUES (:u,:a,:d)');
        $ok = $stmt->execute([':u'=>$this->user_id,':a'=>$this->action,':d'=>$this->description]);
        if ($ok) $this->id = $pdo->lastInsertId();
        return $ok;
    }
}

?>
