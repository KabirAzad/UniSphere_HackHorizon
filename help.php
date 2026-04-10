<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle creating new ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_ticket'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (!empty($subject) && !empty($message)) {
        // Generate random ticket number
        $ticket_number = 'UNI-TKT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $stmt = $pdo->prepare("INSERT INTO tickets (ticket_number, user_id, subject, status) VALUES (?, ?, ?, 'OPEN')");
        if ($stmt->execute([$ticket_number, $user_id, $subject])) {
            $ticket_id = $pdo->lastInsertId();
            $stmt_msg = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, message) VALUES (?, 'USER', ?)");
            $stmt_msg->execute([$ticket_id, $message]);
            header("Location: help.php?ticket_id=" . $ticket_id);
            exit();
        } else {
            $error = "Failed to create ticket.";
        }
    } else {
        $error = "Please provide both a subject and a message.";
    }
}

// Handle sending reply
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reply'])) {
    $ticket_id = $_POST['ticket_id'];
    $message = trim($_POST['message']);

    // Check if ticket belongs to user and is OPEN
    $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch();

    if ($ticket && $ticket['status'] == 'OPEN' && !empty($message)) {
        $stmt_msg = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, message) VALUES (?, 'USER', ?)");
        $stmt_msg->execute([$ticket_id, $message]);
        
        // Update ticket updated_at
        $pdo->prepare("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticket_id]);
        
        header("Location: help.php?ticket_id=" . $ticket_id);
        exit();
    }
}

// Handle closing ticket
if (isset($_GET['close_ticket'])) {
    $ticket_id = $_GET['close_ticket'];
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'CLOSED' WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticket_id, $user_id]);
    header("Location: help.php?ticket_id=" . $ticket_id);
    exit();
}

// Fetch user's tickets
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC");
$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll();

$active_ticket_id = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : (count($tickets) > 0 ? $tickets[0]['id'] : null);
if (isset($_GET['new'])) {
    $active_ticket_id = null; // force new ticket view
}

$active_ticket = null;
$messages = [];

if ($active_ticket_id) {
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$active_ticket_id, $user_id]);
    $active_ticket = $stmt->fetch();

    if ($active_ticket) {
        $stmt = $pdo->prepare("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY sent_at ASC");
        $stmt->execute([$active_ticket_id]);
        $messages = $stmt->fetchAll();
    } else {
        $active_ticket_id = null; // invalid ticket
    }
}

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 2rem;">
    <div class="flex-mobile-col" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2.5rem;">Help & Support</h1>
            <p style="color: var(--text-muted);">Chat with our support team to resolve your issues.</p>
        </div>
        <a href="help.php?new=1" class="btn btn-primary"><i class="fas fa-plus"></i> New Ticket</a>
    </div>

    <?php if($error): ?><div class="badge badge-danger" style="margin-bottom: 1rem; width: 100%; text-align: center;"><?php echo $error; ?></div><?php endif; ?>

    <div class="grid grid-3" style="align-items: start; min-height: 60vh;">
        <!-- Sidebar: Ticket List -->
        <div class="glass" style="padding: 1rem; grid-column: span 1; display: flex; flex-direction: column; gap: 10px; max-height: 70vh; overflow-y: auto;">
            <h3 style="margin-bottom: 10px; padding-left: 10px; font-size: 1.2rem;">Your Tickets</h3>
            <?php if(empty($tickets)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 2rem 0; font-size: 0.9rem;">No support tickets yet.</p>
            <?php else: ?>
                <?php foreach($tickets as $t): ?>
                    <a href="help.php?ticket_id=<?php echo $t['id']; ?>" style="text-decoration: none; color: inherit;">
                        <div style="padding: 1rem; border-radius: 12px; border: 1px solid <?php echo ($active_ticket_id == $t['id']) ? 'var(--primary)' : 'rgba(255,255,255,0.05)'; ?>; background: <?php echo ($active_ticket_id == $t['id']) ? 'rgba(59, 130, 246, 0.1)' : 'rgba(0,0,0,0.2)'; ?>; transition: all 0.2s;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <span style="font-weight: bold; font-size: 0.85rem; color: var(--accent);">#<?php echo $t['ticket_number']; ?></span>
                                <?php if($t['status'] == 'OPEN'): ?>
                                    <span class="badge badge-success" style="font-size: 0.6rem; padding: 3px 6px;">OPEN</span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="font-size: 0.6rem; padding: 3px 6px;">CLOSED</span>
                                <?php endif; ?>
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
            <?php if (!$active_ticket_id && isset($_GET['new'])): ?>
                <!-- New Ticket Form -->
                <div style="padding: 2rem; flex: 1;">
                    <h2 style="margin-bottom: 1.5rem; color: var(--primary);">Create a New Ticket</h2>
                    <form action="help.php" method="POST">
                        <input type="hidden" name="create_ticket" value="1">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label>Subject / Issue Type</label>
                            <input type="text" name="subject" class="form-input" placeholder="e.g. Order not delivered, Account issue..." required>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label>Describe your issue</label>
                            <textarea name="message" class="form-input" rows="6" placeholder="Provide as much detail as possible so we can help you faster..." style="resize: none;" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 12px 24px;"><i class="fas fa-paper-plane" style="margin-right: 8px;"></i> Submit Ticket</button>
                    </form>
                </div>
            <?php elseif ($active_ticket): ?>
                <!-- Chat Header -->
                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); border-radius: 16px 16px 0 0;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.2rem;"><?php echo htmlspecialchars($active_ticket['subject']); ?></h3>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Ticket #<?php echo $active_ticket['ticket_number']; ?></span>
                    </div>
                    <?php if($active_ticket['status'] == 'OPEN'): ?>
                        <a href="help.php?close_ticket=<?php echo $active_ticket['id']; ?>" class="btn btn-glass" style="font-size: 0.8rem; padding: 5px 10px; color: var(--danger); border-color: rgba(239, 68, 68, 0.3);" onclick="return confirm('Are you sure you want to close this ticket?');">Mark as Resolved</a>
                    <?php else: ?>
                        <span class="badge badge-danger">CLOSED</span>
                    <?php endif; ?>
                </div>

                <!-- Chat Messages -->
                <div id="chatBox" style="flex: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 15px;">
                    <!-- Intro Bot Message -->
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div style="width: 35px; height: 35px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.9rem; flex-shrink: 0;">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div style="background: rgba(255,255,255,0.05); padding: 12px 16px; border-radius: 18px 18px 18px 0; max-width: 80%; border: 1px solid rgba(255,255,255,0.05);">
                            <p style="margin: 0; font-size: 0.95rem; line-height: 1.5;">Hi! You've opened ticket <strong>#<?php echo $active_ticket['ticket_number']; ?></strong>. An admin will respond here shortly.</p>
                        </div>
                    </div>

                    <?php foreach($messages as $msg): ?>
                        <?php if($msg['sender_type'] == 'ADMIN'): ?>
                            <!-- Admin Message -->
                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                <div style="width: 35px; height: 35px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.9rem; flex-shrink: 0;">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div style="background: rgba(16, 185, 129, 0.1); padding: 12px 16px; border-radius: 18px 18px 18px 0; max-width: 80%; border: 1px solid rgba(16, 185, 129, 0.2);">
                                    <p style="margin: 0; font-size: 0.95rem; line-height: 1.5; color: #fff;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    <span style="font-size: 0.65rem; color: var(--text-muted); margin-top: 5px; display: block;"><?php echo date('M d, g:i A', strtotime($msg['sent_at'])); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- User Message -->
                            <div style="display: flex; gap: 10px; align-items: flex-end; align-self: flex-end; flex-direction: row-reverse;">
                                <div style="background: var(--primary); padding: 12px 16px; border-radius: 18px 18px 0 18px; max-width: 80%; color: white;">
                                    <p style="margin: 0; font-size: 0.95rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
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
                        <form action="help.php" method="POST" style="display: flex; gap: 10px;">
                            <input type="hidden" name="send_reply" value="1">
                            <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">
                            <input type="text" name="message" class="form-input" placeholder="Type a message..." style="margin-bottom: 0; border-radius: 25px; padding-left: 20px;" autocomplete="off" required>
                            <button type="submit" class="btn btn-primary" style="border-radius: 25px; width: 50px; height: 50px; padding: 0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; color: var(--text-muted); padding: 10px;">
                            <i class="fas fa-lock" style="margin-right: 5px;"></i> This ticket has been closed and cannot receive new messages.
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Empty State -->
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted);">
                    <i class="fas fa-headset" style="font-size: 4rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                    <h3 style="margin-bottom: 10px;">How can we help you today?</h3>
                    <p style="margin-bottom: 1.5rem;">Select an existing ticket or create a new one.</p>
                    <a href="help.php?new=1" class="btn btn-primary">Create New Ticket</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
