<?php
require_once __DIR__ . '/Model.php';

class Patient extends Model
{
    private $id;
    private $username;
    private $password;
    private $full_name;
    private $email;
    private $student_number;
    private $created_at;
    private $isAdmin;
    private $status;

    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->password = $data['password'] ?? null;
        $this->full_name = $data['full_name'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->student_number = $data['student_number'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->isAdmin = !empty($data['isAdmin']);
        $this->status = $data['status'] ?? null;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getFullName() { return $this->full_name; }
    public function getEmail() { return $this->email; }
    public function getStudentNumber() { return $this->student_number; }
    public function isAdmin() { return (bool)$this->isAdmin; }

    // Setters (for writable fields)
    public function setUsername($v) { $this->username = $v; }
    public function setFullName($v) { $this->full_name = $v; }
    public function setEmail($v) { $this->email = $v; }
    public function setStudentNumber($v) { $this->student_number = $v; }

    // CRUD
    public function create()
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('INSERT INTO users (username,password,full_name,email,student_number,isAdmin,status) VALUES (:u,:p,:f,:e,:s,:a,:st)');
        $ok = $stmt->execute([
            ':u' => $this->username,
            ':p' => $this->password,
            ':f' => $this->full_name,
            ':e' => $this->email,
            ':s' => $this->student_number,
            ':a' => $this->isAdmin ? 1 : 0,
            ':st' => $this->status ?? 'active'
        ]);
        if ($ok) $this->id = $pdo->lastInsertId();
        return $ok;
    }

    public static function getAll()
    {
        $pdo = self::pdo();
        $stmt = $pdo->query("SELECT * FROM users WHERE role IS NULL OR role <> 'admin' ORDER BY last_name, first_name");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[] = new self($r);
        return $out;
    }

    public static function findById($id)
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new self($row) : null;
    }

    public function update()
    {
        if (!$this->id) return false;
        $pdo = self::pdo();
        $stmt = $pdo->prepare('UPDATE users SET username=:u, full_name=:f, email=:e, student_number=:s, status=:st WHERE id=:id');
        return $stmt->execute([':u'=>$this->username, ':f'=>$this->full_name, ':e'=>$this->email, ':s'=>$this->student_number, ':st'=>$this->status, ':id'=>$this->id]);
    }

    public function delete()
    {
        if (!$this->id) return false;
        $pdo = self::pdo();
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }
}

?>
