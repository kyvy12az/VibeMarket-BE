<?php

class Model
{
    protected $db;
    protected $table;

    public function __construct()
    {
        $this->db = $this->getConnection();
    }

    protected function getConnection()
    {
        global $conn;
        require_once __DIR__ . '/../../config/database.php';
        return $conn;
    }

    public function query($sql, $params = [])
    {
        if (empty($params)) {
            return $this->db->query($sql);
        }

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return false;
        }

        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }

    public function fetchAll($sql, $params = [])
    {
        $result = $this->query($sql, $params);
        if ($result === false) {
            return [];
        }

        if ($result instanceof mysqli_result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }

        $data = $result->get_result();
        return $data ? $data->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function fetchOne($sql, $params = [])
    {
        $result = $this->query($sql, $params);
        if ($result === false) {
            return null;
        }

        if ($result instanceof mysqli_result) {
            return $result->fetch_assoc();
        }

        $data = $result->get_result();
        return $data ? $data->fetch_assoc() : null;
    }

    public function findById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->fetchOne($sql, [$id]);
    }

    public function findAll($limit = null, $offset = 0)
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($limit !== null) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }
        return $this->fetchAll($sql);
    }

    public function count($where = '')
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        $result = $this->fetchOne($sql);
        return $result ? (int)$result['total'] : 0;
    }

    public function insert($data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->query($sql, array_values($data));
        
        return $stmt ? $this->db->insert_id : false;
    }

    public function update($id, $data)
    {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "$column = ?";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$this->table} SET $setClause WHERE id = ?";
        $params = array_values($data);
        $params[] = $id;
        
        $stmt = $this->query($sql, $params);
        return $stmt !== false;
    }

    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->query($sql, [$id]);
        return $stmt !== false;
    }
}
