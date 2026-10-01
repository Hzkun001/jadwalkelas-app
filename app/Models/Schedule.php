<?php

namespace App\Models;

use PDO;

class Schedule {
    public static function getByRoomAndDay(PDO $db, int $roomId, int $day): array {
        $stmt = $db->prepare("SELECT s.*, r.name as room_name, r.code as room_code 
                              FROM schedules s
                              JOIN rooms r ON r.id = s.room_id
                              WHERE s.room_id = :room_id AND s.day_of_week = :day
                              ORDER BY s.start_time ASC");
        $stmt->execute([
            ':room_id' => $roomId,
            ':day' => $day,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByDay(PDO $db, int $day): array {
        $stmt = $db->prepare("SELECT s.*, r.name as room_name, r.code as room_code 
                              FROM schedules s
                              JOIN rooms r ON r.id = s.room_id
                              WHERE s.day_of_week = :day
                              ORDER BY r.code ASC, s.start_time ASC");
        $stmt->execute([':day' => $day]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAll(PDO $db): array {
        $stmt = $db->query("SELECT s.*, r.name as room_name, r.code as room_code 
                            FROM schedules s
                            JOIN rooms r ON r.id = s.room_id
                            ORDER BY s.day_of_week ASC, s.start_time ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(PDO $db, int $id): ?array {
        $stmt = $db->prepare("SELECT s.*, r.name as room_name, r.code as room_code 
                              FROM schedules s
                              JOIN rooms r ON r.id = s.room_id
                              WHERE s.id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(PDO $db, array $data): int {
        $stmt = $db->prepare("INSERT INTO schedules (room_id, course_name, class_name, lecturer_name, 
                                                    day_of_week, start_time, end_time, academic_period, created_by)
                              VALUES (:room_id, :course_name, :class_name, :lecturer_name, 
                                      :day_of_week, :start_time, :end_time, :academic_period, :created_by)");
        $stmt->execute([
            ':room_id' => $data['room_id'],
            ':course_name' => $data['course_name'],
            ':class_name' => $data['class_name'],
            ':lecturer_name' => $data['lecturer_name'] ?? null,
            ':day_of_week' => $data['day_of_week'],
            ':start_time' => $data['start_time'],
            ':end_time' => $data['end_time'],
            ':academic_period' => $data['academic_period'] ?? '2026/2027 Ganjil',
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function delete(PDO $db, int $id): bool {
        $stmt = $db->prepare("DELETE FROM schedules WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
