<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'ensao_grades';
$username = 'root';
$password = '';

// Connect to database using basic MySQL
$connection = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$connection) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($connection, "utf8mb4");

// Get teacher ID (using first teacher for demo)
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : 'ENS-2025-423';

// Get teacher information
$teacher_query = "SELECT * FROM teachers WHERE teacher_id = '$teacher_id'";
$teacher_result = mysqli_query($connection, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);

if (!$teacher) {
    die("Teacher not found");
}

// Get selected filters
$selected_year = isset($_GET['year']) ? $_GET['year'] : '2024/2025';
$selected_semester = isset($_GET['semester']) ? $_GET['semester'] : 'S1';
$selected_module = isset($_GET['module']) ? $_GET['module'] : 'prog';

// Get all academic years
$years_query = "SELECT DISTINCT academic_year FROM grades ORDER BY academic_year DESC";
$years_result = mysqli_query($connection, $years_query);
$academic_years = array();
while ($row = mysqli_fetch_assoc($years_result)) {
    $academic_years[] = $row['academic_year'];
}

// If no years in database, provide default
if (empty($academic_years)) {
    $academic_years = array('2024/2025', '2023/2024', '2022/2023');
}

// Get all modules
$modules_query = "SELECT module_code, module_name FROM modules ORDER BY module_name";
$modules_result = mysqli_query($connection, $modules_query);
$modules = array();
while ($row = mysqli_fetch_assoc($modules_result)) {
    $modules[] = $row;
}

// Get students for the selected teacher
$students_query = "SELECT DISTINCT s.student_id, s.full_name, s.program
                   FROM students s
                   LEFT JOIN grades g ON s.student_id = g.student_id 
                   WHERE g.teacher_id = '$teacher_id' OR g.teacher_id IS NULL
                   ORDER BY s.full_name";
$students_result = mysqli_query($connection, $students_query);
$students = array();
while ($row = mysqli_fetch_assoc($students_result)) {
    $students[] = $row;
}

// Get existing grades for the selected criteria
$grades_query = "SELECT student_id, controle_continu, tp, projet, examen, note_finale
                 FROM grades 
                 WHERE teacher_id = '$teacher_id' 
                 AND academic_year = '$selected_year' 
                 AND semester = '$selected_semester' 
                 AND module = '$selected_module'";
$grades_result = mysqli_query($connection, $grades_query);
$existing_grades = array();
while ($row = mysqli_fetch_assoc($grades_result)) {
    $existing_grades[$row['student_id']] = $row;
}

// Get teacher statistics
$stats_query = "SELECT 
                    COUNT(DISTINCT g.module) as module_count,
                    COUNT(DISTINCT g.student_id) as student_count
                FROM grades g 
                WHERE g.teacher_id = '$teacher_id'";
$stats_result = mysqli_query($connection, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle form submission for saving grades
if (isset($_POST['grades']) && !empty($_POST['grades'])) {
    $success = true;
    $error_message = '';
    
    foreach ($_POST['grades'] as $student_id => $grade_data) {
        // Clean the input data
        $controle_continu = !empty($grade_data['controle_continu']) ? $grade_data['controle_continu'] : 'NULL';
        $tp = !empty($grade_data['tp']) ? $grade_data['tp'] : 'NULL';
        $projet = !empty($grade_data['projet']) ? $grade_data['projet'] : 'NULL';
        $examen = !empty($grade_data['examen']) ? $grade_data['examen'] : 'NULL';
        $note_finale = !empty($grade_data['note_finale']) ? $grade_data['note_finale'] : 'NULL';
        
        // Check if grade record already exists
        $check_query = "SELECT id FROM grades 
                        WHERE student_id = '$student_id' 
                        AND teacher_id = '$teacher_id' 
                        AND academic_year = '$selected_year' 
                        AND semester = '$selected_semester' 
                        AND module = '$selected_module'";
        $check_result = mysqli_query($connection, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update existing record
            $update_query = "UPDATE grades SET 
                            controle_continu = $controle_continu,
                            tp = $tp,
                            projet = $projet,
                            examen = $examen,
                            note_finale = $note_finale
                            WHERE student_id = '$student_id' 
                            AND teacher_id = '$teacher_id' 
                            AND academic_year = '$selected_year' 
                            AND semester = '$selected_semester' 
                            AND module = '$selected_module'";
            
            if (!mysqli_query($connection, $update_query)) {
                $success = false;
                $error_message = mysqli_error($connection);
                break;
            }
        } else {
            // Insert new record
            $insert_query = "INSERT INTO grades (student_id, teacher_id, academic_year, semester, module, controle_continu, tp, projet, examen, note_finale)
                            VALUES ('$student_id', '$teacher_id', '$selected_year', '$selected_semester', '$selected_module', $controle_continu, $tp, $projet, $examen, $note_finale)";
            
            if (!mysqli_query($connection, $insert_query)) {
                $success = false;
                $error_message = mysqli_error($connection);
                break;
            }
        }
    }
    
    if ($success) {
        echo "<script>alert('Notes sauvegardées avec succès!');</script>";
        
        // Refresh the existing grades after save
        $grades_result = mysqli_query($connection, $grades_query);
        $existing_grades = array();
        while ($row = mysqli_fetch_assoc($grades_result)) {
            $existing_grades[$row['student_id']] = $row;
        }
    } else {
        echo "<script>alert('Erreur lors de la sauvegarde: " . addslashes($error_message) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ENSAO | Enseignant</title>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
      rel="stylesheet"
    />
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
</head>
  <body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="#"><img src="image/ensao.png" alt="ENSAO"></a>
        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#navbarNav"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav mx-auto">
            <li class="nav-item">
              <a class="nav-link" href="dashboard.php"
                ><i class="fas fa-home"></i> Tableau de bord</a
              >
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="notes.php"
                ><i class="fas fa-chart-bar"></i> Notes</a
              >
            </li>
          </ul>
          <div class="user-profile">
            <img src="image/Avatar.png" alt="Avatar Enseignant" />
            <span><?php echo htmlspecialchars($teacher['full_name']); ?><br><a class="logout" href="../Login/login.html">Logout</a></span>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
      <div class="container">
        <div class="welcome-card">
          <h2>Bienvenue, <?php echo htmlspecialchars($teacher['full_name']); ?>!</h2>
          <p>
            Vous avez <?php echo count($modules); ?> modules actifs ce semestre. 
            <?php echo count($students); ?> étudiants sont inscrits dans vos cours.
          </p>
        </div>

        <div class="row">
          <!-- Grade Management Section -->
          <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card mb-4">
              <div class="profile-card">
                <div class="avatar">
                  <img src="image/Avatar.png" alt="Profil Enseignant" />
                </div>
                <h4><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
                <p class="id">ID Enseignant: <?php echo htmlspecialchars($teacher['teacher_id']); ?></p>
                <div class="info">
                  <div class="info-item">
                    <h5>5</h5>
                    <p>Classes</p>
                  </div>
                  <div class="info-item">
                    <h5><?php echo $stats['module_count'] ? $stats['module_count'] : 0; ?></h5>
                    <p>Modules</p>
                  </div>
                  <div class="info-item">
                    <h5><?php echo $stats['student_count'] ? $stats['student_count'] : 0; ?></h5>
                    <p>Étudiants</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="mb-0">Saisie des Notes</h5>
              </div>
              <div class="card-body">
                <form method="GET" id="filterForm">
                  <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($teacher_id); ?>">
                  <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                      <label for="academicYear" class="form-label">Année universitaire:</label>
                      <select class="form-select" id="academicYear" name="year" onchange="document.getElementById('filterForm').submit()">
                        <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($year); ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label for="semester" class="form-label">Semestre:</label>
                      <select class="form-select" id="semester" name="semester" onchange="document.getElementById('filterForm').submit()">
                        <option value="S1" <?php echo $selected_semester == 'S1' ? 'selected' : ''; ?>>S1</option>
                        <option value="S2" <?php echo $selected_semester == 'S2' ? 'selected' : ''; ?>>S2</option>
                      </select>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label for="module" class="form-label">Module:</label>
                      <select class="form-select" id="module" name="module" onchange="document.getElementById('filterForm').submit()">
                        <?php foreach ($modules as $module): ?>
                        <option value="<?php echo htmlspecialchars($module['module_code']); ?>" <?php echo $module['module_code'] == $selected_module ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($module['module_name']); ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </form>
                
                <form method="POST">
                  <div class="table-responsive">
                    <table class="table table-hover table-grades">
                      <thead class="table-light">
                        <tr>
                          <th>Numéro<br>D'apogée</th>
                          <th>Nom complet</th>
                          <th>Contrôle <br>Continu</th>
                          <th>TP</th>
                          <th>Projet</th>
                          <th>Examen</th>
                          <th>Note Final</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($students as $student): 
                          $student_grades = isset($existing_grades[$student['student_id']]) ? $existing_grades[$student['student_id']] : array();
                        ?>
                        <tr>
                          <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                          <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                          <td>
                            <input type="number" step="0.01" min="0" max="20" 
                                   class="form-control" 
                                   name="grades[<?php echo htmlspecialchars($student['student_id']); ?>][controle_continu]"
                                   value="<?php echo isset($student_grades['controle_continu']) ? htmlspecialchars($student_grades['controle_continu']) : ''; ?>" />
                          </td>
                          <td>
                            <input type="number" step="0.01" min="0" max="20" 
                                   class="form-control" 
                                   name="grades[<?php echo htmlspecialchars($student['student_id']); ?>][tp]"
                                   value="<?php echo isset($student_grades['tp']) ? htmlspecialchars($student_grades['tp']) : ''; ?>" />
                          </td>
                          <td>
                            <input type="number" step="0.01" min="0" max="20" 
                                   class="form-control" 
                                   name="grades[<?php echo htmlspecialchars($student['student_id']); ?>][projet]"
                                   value="<?php echo isset($student_grades['projet']) ? htmlspecialchars($student_grades['projet']) : ''; ?>" />
                          </td>
                          <td>
                            <input type="number" step="0.01" min="0" max="20" 
                                   class="form-control" 
                                   name="grades[<?php echo htmlspecialchars($student['student_id']); ?>][examen]"
                                   value="<?php echo isset($student_grades['examen']) ? htmlspecialchars($student_grades['examen']) : ''; ?>" />
                          </td>
                          <td>
                            <input type="number" step="0.01" min="0" max="20" 
                                   class="form-control" 
                                   name="grades[<?php echo htmlspecialchars($student['student_id']); ?>][note_finale]"
                                   value="<?php echo isset($student_grades['note_finale']) ? htmlspecialchars($student_grades['note_finale']) : ''; ?>" />
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <div class="text-center">
                    <button type="submit" class="btn btn-success validate-btn">
                      <i class="fas fa-check-circle me-2"></i>Valider les notes
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>

<?php
// Close database connection
mysqli_close($connection);
?>