<?php
require_once 'includes/config.php';

if (!isLoggedIn() || !hasRole('ADMIN')) {
    header("Location: admin_login.php");
    exit();
}

$error = '';
$success = '';

// Handle sending reply from Admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reply'])) {
    $ticket_id = $_POST['ticket_id'];
    $message = trim($_POST['message']);

    $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if ($ticket && $ticket['status'] == 'OPEN' && !empty($message)) {
        $stmt_msg = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, message) VALUES (?, 'ADMIN', ?)");
        $stmt_msg->execute([$ticket_id, $message]);
        
        $pdo->prepare("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticket_id]);
        
        header("Location: admin_tickets.php?ticket_id=" . $ticket_id);
        exit();
    }
}

// Handle closing ticket from Admin
if (isset($_GET['close_ticket'])) {
    $ticket_id = $_GET['close_ticket'];
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'CLOSED' WHERE id = ?");
    $stmt->execute([$ticket_id]);
    header("Location: admin_tickets.php?ticket_id=" . $ticket_id);
    exit();
}

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'OPEN';

// Fetch Tickets
$stmt = $pdo->prepare("SELECT t.*, u.name as user_name FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.status = ? ORDER BY t.updated_at DESC");
$stmt->execute([$status_filter]);
$tickets = $stmt->fetchAll();

$active_ticket_id = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : (count($tickets) > 0 ? $tickets[0]['id'] : null);
$active_ticket = null;
$messages = [];

if ($active_ticket_id) {
    $stmt = $pdo->prepare("SELECT t.*, u.name as user_name FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt->execute([$active_ticket_id]);
    $active_ticket = $stmt->fetch();

    if ($active_ticket) {
        $stmt = $pdo->prepare("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY sent_at ASC");
        $stmt->execute([$active_ticket_id]);
        $messages = $stmt->fetchAll();
    } else {
        $active_ticket_id = null;
    }
}

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 2rem;">
    <div class="flex-mobile-col" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2.5rem;">Support Tickets</h1>
            <p style="color: var(--text-muted);">Manage and resolve user inquiries.</p>
        </div>
        <div>
            <a href="admin_dashboard.php" class="btn btn-glass" style="margin-right: 10px;">Back to Dashboard</a>
        </div>
    </div>

    <div style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
        <a href="admin_tickets.php?status=OPEN" class="btn <?php echo ($status_filter == 'OPEN') ? 'btn-primary' : 'btn-glass'; ?>">Open Tickets</a>
        <a href="admin_tickets.php?status=CLOSED" class="btn <?php echo ($status_filter == 'CLOSED') ? 'btn-primary' : 'btn-glass'; ?>">Closed Tickets</a>
    </div>

    <div class="grid grid-3" style="align-items: start; min-height: 60vh;">
        <!-- Sidebar: Ticket List -->
        <div class="glass" style="padding: 1rem; grid-column: span 1; display: flex; flex-direction: column; gap: 10px; max-height: 70vh; overflow-y: auto;">
            <?php if(empty($tickets)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 2rem 0; font-size: 0.9rem;">No <?php echo strtolower($status_filter); ?> tickets found.</p>
            <?php else: ?>
                <?php foreach($tickets as $t): ?>
                    <a href="admin_tickets.php?ticket_id=<?php echo $t['id']; ?>&status=<?php echo $status_filter; ?>" style="text-decoration: none; color: inherit;">
                        <div style="padding: 1rem; border-radius: 12px; border: 1px solid <?php echo ($active_ticket_id == $t['id']) ? 'var(--primary)' : 'rgba(255,255,255,0.05)'; ?>; background: <?php echo ($active_ticket_id == $t['id']) ? 'rgba(59, 130, 246, 0.1)' : 'rgba(0,0,0,0.2)'; ?>; transition: all 0.2s;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <span style="font-weight: bold; font-size: 0.85rem; color: var(--accent);">#<?php echo $t['ticket_number']; ?></span>
                                <span style="font-size: 0.75rem; color: var(--text-main);"><i class="fas fa-user" style="font-size: 0.6rem; color: var(--text-muted);"></i> <?php echo htmlspecialchars($t['user_name']); ?></span>
                            </div>
                            <h4 style="font-size: 1rem; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($t['subject']); ?></h4>
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 5px;">
                                Last updated: <?php echo date('M d, g:i A', strtotime($t['updated_at'])); ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Main Chat Area -->
        <div class="glass" style="grid-column: span 2; display: flex; flex-direction: column; height: 70vh;">
            <?php if ($active_ticket): ?>
                <!-- Chat Header -->
                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); border-radius: 16px 16px 0 0;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.2rem;"><?php echo htmlspecialchars($active_ticket['subject']); ?></h3>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">User: <strong><?php echo htmlspecialchars($active_ticket['user_name']); ?></strong> | Ticket #<?php echo $active_ticket['ticket_number']; ?></span>
                    </div>
                    <?php if($active_ticket['status'] == 'OPEN'): ?>
                        <a href="admin_tickets.php?close_ticket=<?php echo $active_ticket['id']; ?>" class="btn btn-glass" style="font-size: 0.8rem; padding: 5px 10px; color: var(--danger); border-color: rgba(239, 68, 68, 0.3);" onclick="return confirm('Are you sure you want to FORCE CLOSE this ticket?');">Force Close</a>
                    <?php else: ?>
                        <span class="badge badge-danger">CLOSED</span>
                    <?php endif; ?>
                </div>

                <!-- Chat Messages -->
                <div id="chatBox" style="flex: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 15px;">
                    <?php if(empty($messages)): ?>
                        <div style="text-align: center; color: var(--text-muted); font-size: 0.9rem; padding: 2rem;">No messages recorded yet.</div>
                    <?php endif; ?>

                    <?php foreach($messages as $msg): ?>
                        <?php if($msg['sender_type'] == 'USER'): ?>
                            <!-- User Message -->
                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                <div style="width: 35px; height: 35px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.9rem; flex-shrink: 0;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div style="background: rgba(255,255,255,0.05); padding: 12px 16px; border-radius: 18px 18px 18px 0; max-width: 80%; border: 1px solid rgba(255,255,255,0.05);">
                                    <p style="margin: 0; font-size: 0.95rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    <span style="font-size: 0.65rem; color: var(--text-muted); margin-top: 5px; display: block;"><?php echo date('M d, g:i A', strtotime($msg['sent_at'])); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Admin Message -->
                            <div style="display: flex; gap: 10px; align-items: flex-end; align-self: flex-end; flex-direction: row-reverse;">
                                <div style="width: 35px; height: 35px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.9rem; flex-shrink: 0;">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div style="background: rgba(16, 185, 129, 0.1); padding: 12px 16px; border-radius: 18px 18px 0 18px; max-width: 80%; border: 1px solid rgba(16, 185, 129, 0.2);">
                                    <p style="margin: 0; font-size: 0.95rem; line-height: 1.5; color: #fff;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    <span style="font-size: 0.65rem; color: rgba(255,255,255,0.7); margin-top: 5px; display: block; text-align: right;"><?php echo date('M d, g:i A', strtotime($msg['sent_at'])); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Chat Input Focus Fixes -->
                <script>
                    var chatBox = document.getElementById("chatBox");
                    chatBox.scrollTop = chatBox.scrollHeight;
                </script>

                <!-- Input Area -->
                <div style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.2); border-radius: 0 0 16px 16px;">
                    <?php if($active_ticket['status'] == 'OPEN'): ?>
                        <form action="admin_tickets.php" method="POST" style="display: flex; gap: 10px;">
                            <input type="hidden" name="send_reply" value="1">
                            <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">
                            <input type="text" name="message" class="form-input" placeholder="Type a response to the user..." style="margin-bottom: 0; border-radius: 25px; padding-left: 20px;" autocomplete="off" required>
                            <button type="submit" class="btn btn-primary" style="border-radius: 25px; width: 50px; height: 50px; padding: 0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--accent);"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; color: var(--text-muted); padding: 10px;">
                            <i class="fas fa-lock" style="margin-right: 5px;"></i> Ticket closed.
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Empty State -->
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 4rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                    <h3 style="margin-bottom: 10px;">No ticket selected</h3>
                    <p style="margin-bottom: 1.5rem;">Select a ticket from the sidebar to view messages.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
