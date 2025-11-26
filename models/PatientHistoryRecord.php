<?php
require_once __DIR__ . '/Model.php';

class PatientHistoryRecord extends Model
{
    private $id;
    private $patient_id;
    private $visit_date;
    private $record_type;
    private $symptoms;
    private $diagnosis;
    private $treatment;
    private $notes;
    private $vital_signs;
    private $created_at;
    private $created_by;

    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->patient_id = $data['patient_id'] ?? null;
        $this->visit_date = $data['visit_date'] ?? null;
        $this->record_type = $data['record_type'] ?? null;
        $this->symptoms = $data['symptoms'] ?? null;
        $this->diagnosis = $data['diagnosis'] ?? null;
        $this->treatment = $data['treatment'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->vital_signs = $data['vital_signs'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->created_by = $data['created_by'] ?? null;
    }

    public function getId() { return $this->id; }
    public function getPatientId() { return $this->patient_id; }
    public function getVisitDate() { return $this->visit_date; }
    public function getDiagnosis() { return $this->diagnosis; }
    public function getNotes() { return $this->notes; }

    public function create()
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('INSERT INTO patient_history_records (patient_id,visit_date,record_type,symptoms,diagnosis,treatment,notes,vital_signs,created_by) VALUES (:pid,:vd,:rt,:sym,:diag,:treat,:notes,:vitals,:cb)');
        $ok = $stmt->execute([
            ':pid'=>$this->patient_id,
            ':vd'=>$this->visit_date,
            ':rt'=>$this->record_type,
            ':sym'=>$this->symptoms,
            ':diag'=>$this->diagnosis,
            ':treat'=>$this->treatment,
            ':notes'=>$this->notes,
            ':vitals'=>$this->vital_signs,
            ':cb'=>$this->created_by,
        ]);
        if ($ok) $this->id = $pdo->lastInsertId();
        return $ok;
    }

    public static function getAllForPatient($patient_id)
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('SELECT * FROM patient_history_records WHERE patient_id = :pid ORDER BY created_at DESC');
        $stmt->execute([':pid'=>$patient_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[] = new self($r);
        return $out;
    }
}

?>
