<?php
require_once __DIR__ . '/Model.php';

class Biometric extends Model
{
    private $id;
    private $user_id;
    private $height;
    private $weight;
    private $blood_pressure;
    private $temperature;
    private $pulse_rate;
    private $respiratory_rate;
    private $nurse_recommendation;
    private $record_date;
    private $created_at;

    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
        $this->height = $data['height'] ?? null;
        $this->weight = $data['weight'] ?? null;
        $this->blood_pressure = $data['blood_pressure'] ?? null;
        $this->temperature = $data['temperature'] ?? null;
        $this->pulse_rate = $data['pulse_rate'] ?? null;
        $this->respiratory_rate = $data['respiratory_rate'] ?? null;
        $this->nurse_recommendation = $data['nurse_recommendation'] ?? null;
        $this->record_date = $data['record_date'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getHeight() { return $this->height; }
    public function getWeight() { return $this->weight; }
    public function getBloodPressure() { return $this->blood_pressure; }
    public function getTemperature() { return $this->temperature; }
    public function getPulseRate() { return $this->pulse_rate; }
    public function getRespiratoryRate() { return $this->respiratory_rate; }
    public function getNurseRecommendation() { return $this->nurse_recommendation; }
    public function getRecordDate() { return $this->record_date; }

    // Setters
    public function setUserId($v) { $this->user_id = $v; }
    public function setHeight($v) { $this->height = $v; }
    public function setWeight($v) { $this->weight = $v; }
    public function setBloodPressure($v) { $this->blood_pressure = $v; }
    public function setTemperature($v) { $this->temperature = $v; }
    public function setPulseRate($v) { $this->pulse_rate = $v; }
    public function setRespiratoryRate($v) { $this->respiratory_rate = $v; }
    public function setNurseRecommendation($v) { $this->nurse_recommendation = $v; }
    public function setRecordDate($v) { $this->record_date = $v; }

    public function create()
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('INSERT INTO biometrics (user_id,height,weight,blood_pressure,temperature,pulse_rate,respiratory_rate,nurse_recommendation,record_date) VALUES (:u,:h,:w,:bp,:t,:pr,:rr,:nr,:rd)');
        $ok = $stmt->execute([
            ':u' => $this->user_id,
            ':h' => $this->height,
            ':w' => $this->weight,
            ':bp' => $this->blood_pressure,
            ':t' => $this->temperature,
            ':pr' => $this->pulse_rate,
            ':rr' => $this->respiratory_rate,
            ':nr' => $this->nurse_recommendation,
            ':rd' => $this->record_date,
        ]);
        if ($ok) $this->id = $pdo->lastInsertId();
        return $ok;
    }

    public static function getAll()
    {
        $pdo = self::pdo();
        $stmt = $pdo->query('SELECT * FROM biometrics ORDER BY record_date DESC, created_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[] = new self($r);
        return $out;
    }

    /**
     * Return all biometric records as associative arrays.
     * Fields are normalized to the names used by the UI:
     *  - patient_name (users.full_name)
     *  - temperature
     *  - heart_rate (maps to pulse_rate)
     *  - blood_pressure
     *  - date_recorded (maps to record_date)
     *
     * Returns an empty array if no records found.
     */
    public static function getAllBiometrics()
    {
        $pdo = self::pdo();
        $sql = "SELECT b.id,
                       u.full_name AS patient_name,
                       b.temperature,
                       b.pulse_rate AS heart_rate,
                       b.blood_pressure,
                       b.record_date AS date_recorded,
                       b.created_at
                FROM biometrics b
                LEFT JOIN users u ON b.user_id = u.id
                ORDER BY b.record_date DESC, b.created_at DESC";

        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute()) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    public static function findById($id)
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('SELECT * FROM biometrics WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new self($row) : null;
    }

    public function update()
    {
        if (!$this->id) return false;
        $pdo = self::pdo();
    $stmt = $pdo->prepare('UPDATE biometrics SET height=:h, weight=:w, blood_pressure=:bp, temperature=:t, pulse_rate=:pr, respiratory_rate=:rr, nurse_recommendation=:nr, record_date=:rd WHERE id=:id');
    return $stmt->execute([':h'=>$this->height,':w'=>$this->weight,':bp'=>$this->blood_pressure,':t'=>$this->temperature,':pr'=>$this->pulse_rate,':rr'=>$this->respiratory_rate,':nr'=>$this->nurse_recommendation,':rd'=>$this->record_date,':id'=>$this->id]);
    }

    public function delete()
    {
        if (!$this->id) return false;
        $pdo = self::pdo();
        $stmt = $pdo->prepare('DELETE FROM biometrics WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    /**
     * Get latest vitals for each user
     */
    public static function getLatestVitals()
    {
        $pdo = self::pdo();
        $sql = "SELECT b.*, u.full_name, u.email, u.phone, u.department_college_institute, u.role
                FROM biometrics b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.id IN (
                    SELECT MAX(id) FROM biometrics GROUP BY user_id
                )
                ORDER BY b.record_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get recent biometric records
     */
    public static function getRecentRecords($limit = 5)
    {
        $pdo = self::pdo();
        $sql = "SELECT b.*, u.full_name, u.email
                FROM biometrics b
                LEFT JOIN users u ON b.user_id = u.id
                ORDER BY b.record_date DESC, b.created_at DESC
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get count of recent records (within last N hours)
     */
    public static function getRecentCount($hours = 24)
    {
        $pdo = self::pdo();
        $sql = "SELECT COUNT(*) as count FROM biometrics 
                WHERE record_date >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':hours', (int)$hours, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get average blood pressure (within last N hours)
     */
    public static function getAverageBloodPressure($hours = 24)
    {
        $pdo = self::pdo();
        $sql = "SELECT 
                    SUBSTRING_INDEX(SUBSTRING_INDEX(AVG(CAST(SUBSTRING_INDEX(blood_pressure, '/', 1) AS DECIMAL(10,2))), '.', 1), '.', -1) as systolic,
                    SUBSTRING_INDEX(SUBSTRING_INDEX(AVG(CAST(SUBSTRING_INDEX(blood_pressure, '/', -1) AS DECIMAL(10,2))), '.', 1), '.', -1) as diastolic
                FROM biometrics 
                WHERE record_date >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                AND blood_pressure IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':hours', (int)$hours, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['systolic' => $result['systolic'] ?? 0, 'diastolic' => $result['diastolic'] ?? 0];
    }
}

?>
