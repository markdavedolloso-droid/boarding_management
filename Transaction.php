<?php
// Transaction.php
require_once 'connection.php';

class Transaction {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

   public function transferMoney($sender_id, $receiver_id, $amount) {
    try {
        $this->conn->beginTransaction();

        // Deduct from sender
        $stmt = $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE user_id = ?");
        $stmt->execute([$amount, $sender_id]);

        // Check sender balance
        $check = $this->conn->prepare("SELECT balance FROM users WHERE user_id = ?");
        $check->execute([$sender_id]);
        $balance = $check->fetchColumn();

        if ($balance < 0) {
            throw new Exception("Insufficient funds for user ID: $sender_id");
        }

        // Check if receiver exists before adding money
        $checkReceiver = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
        $checkReceiver->execute([$receiver_id]);
        if ($checkReceiver->fetchColumn() == 0) {
            throw new Exception("Receiver ID $receiver_id does not exist");
        }

        // Add to receiver
        $stmt = $this->conn->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$amount, $receiver_id]);

        $this->conn->commit();
        return ["status" => "success", "message" => "₱$amount transferred successfully."];

    } catch (Exception $e) {
        $this->conn->rollBack();
        return ["status" => "error", "message" => $e->getMessage()];
    }
}


    public function getBalances() {
        $stmt = $this->conn->query("SELECT * FROM users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
