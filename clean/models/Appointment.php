<?php
require_once __DIR__ . '/Model.php';

class Appointment extends Model
{
    private $id;
    private $user_id;
    private $service_id;
    private $appointment_date;
    private $appointment_time;
    private $reason;
    private $status;
    private $created_at;

    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
        $this->service_id = $data['service_id'] ?? null;
        $this->appointment_date = $data['appointment_date'] ?? null;
        $this->appointment_time = $data['appointment_time'] ?? null;
        $this->reason = $data['reason'] ?? null;
        $this->status = $data['status'] ?? 'pending';
        $this->created_at = $data['created_at'] ?? null;
    }

    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getStatus() { return $this->status; }

    public function create()
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('INSERT INTO appointments (user_id,service_id,appointment_date,appointment_time,reason,status) VALUES (:u,:s,:d,:t,:r,:st)');
        $ok = $stmt->execute([':u'=>$this->user_id,':s'=>$this->service_id,':d'=>$this->appointment_date,':t'=>$this->appointment_time,':r'=>$this->reason,':st'=>$this->status]);
        if ($ok) $this->id = $pdo->lastInsertId();
        return $ok;
    }

    public static function findById($id)
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = :id LIMIT 1');
        $stmt->execute([':id'=>$id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? new self($r) : null;
    }

    public function updateStatus($new)
    {
        if (!$this->id) return false;
        $pdo = self::pdo();
        $stmt = $pdo->prepare('UPDATE appointments SET status = :s WHERE id = :id');
        $ok = $stmt->execute([':s'=>$new,':id'=>$this->id]);
        if ($ok) $this->status = $new;
        return $ok;
    }
}

?>
