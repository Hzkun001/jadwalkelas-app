<?php

namespace App\Models;

use PDO;

class EventBooking {
    public static function getAll(PDO $db): array {
        $stmt = $db->query("SELECT b.*, r.name as room_name, r.code as room_code, u.name as user_name 
                            FROM event_bookings b
                            JOIN rooms r ON r.id = b.room_id
                            LEFT JOIN users u ON u.id = b.user_id
                            ORDER BY b.booking_date DESC, b.start_time DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getPending(PDO $db): array {
        $stmt = $db->query("SELECT b.*, r.name as room_name, r.code as room_code, u.name as user_name 
                            FROM event_bookings b
                            JOIN rooms r ON r.id = b.room_id
                            LEFT JOIN users u ON u.id = b.user_id
                            WHERE b.status = 'pending'
                            ORDER BY b.booking_date ASC, b.start_time ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByDateRange(PDO $db, string $startDate, string $endDate, ?int $roomId = null): array {
        $sql = "SELECT b.*, r.name as room_name, r.code as room_code 
                FROM event_bookings b
                JOIN rooms r ON r.id = b.room_id
                WHERE b.booking_date >= :start_date 
                  AND b.booking_date <= :end_date 
                  AND b.status = 'approved'";
        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ];

        if ($roomId !== null && $roomId > 0) {
            $sql .= " AND b.room_id = :room_id";
            $params[':room_id'] = $roomId;
        }

        $sql .= " ORDER BY b.booking_date ASC, b.start_time ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(PDO $db, int $id): ?array {
        $stmt = $db->prepare("SELECT b.*, r.name as room_name, r.code as room_code 
                              FROM event_bookings b
                              JOIN rooms r ON r.id = b.room_id
                              WHERE b.id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(PDO $db, array $data): int {
        $stmt = $db->prepare("INSERT INTO event_bookings (room_id, event_name, organizer, booking_date, 
                                                         start_time, end_time, description, status, user_id)
                              VALUES (:room_id, :event_name, :organizer, :booking_date, 
                                      :start_time, :end_time, :description, :status, :user_id)");
        $stmt->execute([
            ':room_id' => $data['room_id'],
            ':event_name' => $data['event_name'],
            ':organizer' => $data['organizer'],
            ':booking_date' => $data['booking_date'],
            ':start_time' => $data['start_time'],
            ':end_time' => $data['end_time'],
            ':description' => $data['description'] ?? null,
            ':status' => $data['status'] ?? 'pending',
            ':user_id' => $data['user_id'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function updateStatus(PDO $db, int $id, string $status, ?string $reason = null, ?int $approvedBy = null): bool {
        $stmt = $db->prepare("UPDATE event_bookings 
                              SET status = :status, rejection_reason = :reason, approved_by = :approved_by 
                              WHERE id = :id");
        return $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':reason' => $reason,
            ':approved_by' => $approvedBy,
        ]);
    }
}
