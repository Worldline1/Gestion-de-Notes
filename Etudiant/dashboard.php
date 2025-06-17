<?php
session_start();

// Simple database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ensao_grades';

// Connect to database
$connection = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get student ID from URL or set default
$student_id = 'GI2023457';
if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
}

// Get filters from form
$selected_year = '2024/2025';
$selected_semester = 'S1';

if (isset($_GET['year'])) {
    $selected_year = $_GET['year'];
}
if (isset($_GET['semester'])) {
    $selected_semester = $_GET['semester'];
}

// Get student information
$student_query = "SELECT * FROM students WHERE student_id = '$student_id'";
$student_result = mysqli_query($connection, $student_query);

if (mysqli_num_rows($student_result) == 0) {
    die("Student not found");
}

$student = mysqli_fetch_assoc($student_result);

// Get all academic years for this student
$years_query = "SELECT DISTINCT academic_year FROM grades WHERE student_id = '$student_id' ORDER BY academic_year DESC";
$years_result = mysqli_query($connection, $years_query);

$academic_years = array();
while ($row = mysqli_fetch_assoc($years_result)) {
    $academic_years[] = $row['academic_year'];
}

// If no years found, add default years
if (empty($academic_years)) {
    $academic_years = array('2024/2025', '2023/2024');
}

// Get grades with calculated final note
$grades_query = "
    SELECT 
        g.*,
        m.module_name,
        t.full_name as teacher_name,
        COALESCE(
            (COALESCE(g.controle_continu, 0) * 0.3 +
             COALESCE(g.tp, 0) * 0.2 +
             COALESCE(g.projet, 0) * 0.25 +
             COALESCE(g.examen, 0) * 0.25), 0
        ) as calculated_final_note
    FROM grades g
    LEFT JOIN modules m ON g.module = m.module_code
    LEFT JOIN teachers t ON g.teacher_id = t.teacher_id
    WHERE g.student_id = '$student_id' 
    AND g.academic_year = '$selected_year' 
    AND g.semester = '$selected_semester'
    ORDER BY m.module_name
";

$grades_result = mysqli_query($connection, $grades_query);
$grades = array();

while ($row = mysqli_fetch_assoc($grades_result)) {
    $grades[] = $row;
}

// Calculate statistics
$total_modules = count($grades);
$total_points = 0;
$valid_grades = 0;

foreach ($grades as $grade) {
    if ($grade['calculated_final_note'] > 0) {
        $total_points += $grade['calculated_final_note'];
        $valid_grades++;
    }
}

$overall_average = 0;
if ($valid_grades > 0) {
    $overall_average = $total_points / $valid_grades;
}

// Calculate ranking
$ranking_query = "
    SELECT 
        student_id, 
        AVG(COALESCE(
            (COALESCE(controle_continu, 0) * 0.3 +
             COALESCE(tp, 0) * 0.2 +
             COALESCE(projet, 0) * 0.25 +
             COALESCE(examen, 0) * 0.25), 0
        )) as avg_grade
    FROM grades 
    WHERE academic_year = '$selected_year' 
    AND semester = '$selected_semester'
    GROUP BY student_id
    HAVING AVG(COALESCE(
        (COALESCE(controle_continu, 0) * 0.3 +
         COALESCE(tp, 0) * 0.2 +
         COALESCE(projet, 0) * 0.25 +
         COALESCE(examen, 0) * 0.25), 0
    )) > 0
    ORDER BY avg_grade DESC
";

$ranking_result = mysqli_query($connection, $ranking_query);
$all_averages = array();

while ($row = mysqli_fetch_assoc($ranking_result)) {
    $all_averages[] = $row;
}

$student_rank = 1;
$total_students = count($all_averages);

for ($i = 0; $i < count($all_averages); $i++) {
    if ($all_averages[$i]['student_id'] == $student_id) {
        $student_rank = $i + 1;
        break;
    }
}

// Helper functions
function getGradeBadgeClass($grade) {
    if ($grade == 0 || $grade == '') {
        return 'grade-pending';
    }
    if ($grade >= 16) {
        return 'grade-excellent';
    }
    if ($grade >= 14) {
        return 'grade-good';
    }
    if ($grade >= 10) {
        return 'grade-average';
    }
    return 'grade-poor';
}

function formatGrade($grade) {
    if ($grade == 0 || $grade == '' || $grade == null) {
        return '-';
    }
    return number_format($grade, 1);
}

// Calculate progress percentage
$progress_percentage = 0;
if ($overall_average > 0) {
    $progress_percentage = ($overall_average / 20) * 100;
}

// Get current semester info
$current_semester = $selected_semester;
$semester_number = str_replace('S', '', $current_semester);

// Get first name
$name_parts = explode(' ', $student['full_name']);
$first_name = $name_parts[0];

// Close database connection
mysqli_close($connection);
?>

<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ENSAO | Étudiant</title>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
      rel="stylesheet"
    />
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
  </head>
  <body>
    <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="#">
          <img src="image/ensao.png" alt="Logo" />
        </a>
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
              <a class="nav-link active" href="#"
                ><i class="fas fa-home"></i> Tableau de bord</a
              >
            </li>
          </ul>
          <div class="user-profile">
            <img src="image/Avatar.png" alt="Avatar Étudiant" />
            <span><?php echo htmlspecialchars($student['full_name']); ?><br> <a class="logout" href="../Login/login.html">Logout</a></span>
          </div>
        </div>
      </div>
    </nav>
    <div class="main-content">
      <div class="container">
        <div class="welcome-card">
          <h2>Bienvenue, <?php echo htmlspecialchars($first_name); ?>!</h2>
          <p>
            Consultez vos notes et suivez votre progression académique. 
            <?php 
            if ($student_rank <= ($total_students * 0.25)) {
                echo "Votre performance actuelle vous place dans le top 25% de votre promotion.";
            } elseif ($student_rank <= ($total_students * 0.50)) {
                echo "Votre performance actuelle vous place dans la première moitié de votre promotion.";
            } else {
                echo "Continuez vos efforts pour améliorer votre classement.";
            }
            ?>
          </p>
        </div>

        <div class="row">
          <div class="col-lg-4">
            <div class="card mb-4">
              <div class="profile-card">
                <div class="avatar">
                  <img src="image/Avatar.png" alt="Profil Étudiant" />
                </div>
                <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                <p class="id">Matricule: <?php echo htmlspecialchars($student['student_id']); ?></p>
                <div class="info">
                  <div class="info-item">
                    <h5><?php echo htmlspecialchars($current_semester); ?></h5>
                    <p>Semestre</p>
                  </div>
                  <div class="info-item">
                    <h5><?php echo $total_modules; ?></h5>
                    <p>Cours</p>
                  </div>
                  <div class="info-item">
                    <h5><?php echo number_format($overall_average, 1); ?></h5>
                    <p>Moyenne</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="mb-0">Mes Notes</h5>
              </div>
              <div class="card-body">
                <form method="GET" id="filterForm">
                  <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                  <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                      <label for="academicYear" class="form-label">Année universitaire:</label>
                      <select class="form-select" id="academicYear" name="year" onchange="document.getElementById('filterForm').submit()">
                        <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year == $selected_year) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($year); ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="semester" class="form-label">Semestre:</label>
                      <select class="form-select" id="semester" name="semester" onchange="document.getElementById('filterForm').submit()">
                        <option value="S1" <?php echo ($selected_semester == 'S1') ? 'selected' : ''; ?>>S1</option>
                        <option value="S2" <?php echo ($selected_semester == 'S2') ? 'selected' : ''; ?>>S2</option>
                      </select>
                    </div>
                  </div>
                </form>
                
                <div class="table-responsive">
                  <table class="table table-hover grades-table">
                    <thead class="table-light">
                      <tr>
                        <th>Module</th>
                        <th>Contrôle Continu</th>
                        <th>TP</th>
                        <th>Projet</th>
                        <th>Examen</th>
                        <th>Moyenne</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($grades)): ?>
                      <tr>
                        <td colspan="6" class="text-center text-muted">
                          Aucune note disponible pour cette période
                        </td>
                      </tr>
                      <?php else: ?>
                        <?php foreach ($grades as $grade): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($grade['module_name'] ? $grade['module_name'] : $grade['module']); ?></td>
                          <td><?php echo formatGrade($grade['controle_continu']); ?></td>
                          <td><?php echo formatGrade($grade['tp']); ?></td>
                          <td><?php echo formatGrade($grade['projet']); ?></td>
                          <td><?php echo formatGrade($grade['examen']); ?></td>
                          <td>
                            <span class="grade-badge <?php echo getGradeBadgeClass($grade['calculated_final_note']); ?>">
                              <?php echo formatGrade($grade['calculated_final_note']); ?>
                            </span>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                
                <?php if (!empty($grades) && $overall_average > 0): ?>
                <div class="text-center mt-3">
                  <p class="fw-bold mb-2">
                    Moyenne générale du semestre:
                    <span class="text-primary"><?php echo number_format($overall_average, 2); ?></span>
                  </p>
                  <div class="progress mb-2" style="height: 10px">
                    <div
                      class="progress-bar"
                      role="progressbar"
                      style="width: <?php echo round($progress_percentage); ?>%"
                      aria-valuenow="<?php echo round($progress_percentage); ?>"
                      aria-valuemin="0"
                      aria-valuemax="100"
                    ></div>
                  </div>
                  <small class="text-muted">
                    Classement: <?php echo $student_rank; ?> sur <?php echo $total_students; ?> étudiants
                  </small>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  </body>
</html>