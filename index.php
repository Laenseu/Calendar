<?php
// Database connection
$servername = "36.255.61.12";
$username = "root"; // Default username for XAMPP
$password = "Change_to_Strong_SQL_Password"; // Default password for XAMPP
$dbname = "time_tracking"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL query to get unique users for the dropdown
$userSql = "SELECT DISTINCT user_id, user_name FROM timelogs";
$userResult = $conn->query($userSql);

// Prepare user options for dropdown
$userOptions = [];
if ($userResult->num_rows > 0) {
    while ($userRow = $userResult->fetch_assoc()) {
        $userOptions[] = $userRow;
    }
}

// SQL query for events
$sql = "SELECT 
            user_id, 
            user_name, 
            date AS log_date,
            SUM(duration) AS total_hours,
            CASE 
                WHEN SUM(duration) >= 8 THEN 0
                ELSE 8 - SUM(duration) 
            END AS missing_hours
        FROM 
            timelogs
        WHERE 
            date BETWEEN '2024-01-01' AND CURRENT_DATE
        GROUP BY 
            user_id, user_name, log_date;";

$result = $conn->query($sql);

// Prepare data for FullCalendar
$events = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($row['missing_hours'] > 0) {
            $events[] = [
                'title' => ' Missing Hours - ' . $row['missing_hours'],
                'start' => $row['log_date'],
                'allDay' => true,
                'color' => 'red', // Color for missing hours
                'user_id' => $row['user_id'], // Add user_id to the event
                'user_name' => $row['user_name'], // Add user_name to display on hover
                'total_hours' => $row['total_hours'] // Add total_hours for hover
            ];
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Logs Calendar</title>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <style>
       #calendar {
            max-width: 900px;
            margin: 40px auto;
        }
        #userSelect {
            margin: 20px auto;
            display: block;
            max-width: 300px;
        }
        /* Tooltip styling */
        .fc-tooltip {
            position: absolute;
            background: #333;
            color: #fff;
            padding: 5px;
            border-radius: 3px;
            font-size: 12px;
            z-index: 9999;
            text-align: center;
        
        }
    </style>
</head>
<body>

<!-- Dropdown for user selection -->
<select id="userSelect">
    <option value="">Select User</option>
    <?php foreach ($userOptions as $user): ?>
        <option value="<?php echo $user['user_id']; ?>"><?php echo $user['user_name']; ?></option>
    <?php endforeach; ?>
</select>

<div id='calendar'></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');

        // Function to filter events based on selected user
        function filterEventsByUser(userId) {
            var filteredEvents = <?php echo json_encode($events); ?>; // Get all events
            
            if (userId) {
                filteredEvents = filteredEvents.filter(event => event.user_id == userId);
            }

            return filteredEvents;
        }

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: filterEventsByUser(''), // Show all events by default
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },

            eventMouseEnter: function(info) {
                // Create tooltip element
                tooltip = document.createElement('div');
                tooltip.className = 'fc-tooltip';
                tooltip.innerHTML = `
                    <strong>${info.event.extendedProps.user_name}</strong><br/>
                    Total Hours: ${info.event.extendedProps.total_hours}
                `;
                document.body.appendChild(tooltip);

                // Position the tooltip near the mouse cursor
                tooltip.style.left = info.jsEvent.pageX + 'px';
                tooltip.style.top = info.jsEvent.pageY + 'px';
            },
            eventMouseLeave: function() {
                // Remove tooltip when mouse leaves the event
                if (tooltip) {
                    tooltip.remove();
                    tooltip = null;
                }
            }
        });
        
        calendar.render();

        // Event listener for user selection
        document.getElementById('userSelect').addEventListener('change', function() {
            var selectedUserId = this.value;
            calendar.removeAllEvents(); // Remove current events
            calendar.addEventSource(filterEventsByUser(selectedUserId)); // Add filtered events
        });
         // Move tooltip with the mouse
         document.addEventListener('mousemove', function(e) {
            if (tooltip) {
                tooltip.style.left = e.pageX + 'px';
                tooltip.style.top = e.pageY + 'px';
            }
        });
    });
</script>

</body>
</html>
