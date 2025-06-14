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

// Get current teacher information
$teacher_query = "SELECT * FROM teachers WHERE teacher_id = '$teacher_id'";
$teacher_result = mysqli_query($connection, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);

if (!$teacher) {
    die("Teacher not found");
}

// Get filter parameters
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '2024/2025';
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'S1';
$module = isset($_GET['module']) ? $_GET['module'] : 'algo';

// Get all modules for dropdown
$modules_query = "SELECT * FROM modules ORDER BY module_name";
$modules_result = mysqli_query($connection, $modules_query);
$modules = array();
while ($row = mysqli_fetch_assoc($modules_result)) {
    $modules[] = $row;
}

// Get teacher statistics
$teacher_stats_query = "SELECT 
                            COUNT(DISTINCT module) as total_modules,
                            COUNT(DISTINCT student_id) as total_students,
                            COUNT(DISTINCT CONCAT(academic_year, semester)) as total_classes
                        FROM grades 
                        WHERE teacher_id = '$teacher_id'";
$teacher_stats_result = mysqli_query($connection, $teacher_stats_query);
$teacher_stats = mysqli_fetch_assoc($teacher_stats_result);

// Get students data for current selection
$students_query = "SELECT 
                       g.*,
                       s.full_name as student_name,
                       m.module_name,
                       CASE 
                           WHEN g.note_finale IS NOT NULL THEN g.note_finale
                           ELSE COALESCE(
                               (COALESCE(g.controle_continu, 0) * 0.3 + 
                                COALESCE(g.tp, 0) * 0.2 + 
                                COALESCE(g.projet, 0) * 0.25 + 
                                COALESCE(g.examen, 0) * 0.25), 0
                           )
                       END as calculated_grade
                   FROM grades g
                   JOIN students s ON g.student_id = s.student_id
                   JOIN modules m ON g.module = m.module_code
                   WHERE g.teacher_id = '$teacher_id' 
                   AND g.academic_year = '$academic_year' 
                   AND g.semester = '$semester' 
                   AND g.module = '$module'
                   ORDER BY s.full_name";
$students_result = mysqli_query($connection, $students_query);
$students = array();
while ($row = mysqli_fetch_assoc($students_result)) {
    $students[] = $row;
}

// Calculate statistics for current selection
$statistics = array(
    'total_students' => count($students),
    'passed_students' => 0,
    'failed_students' => 0,
    'pass_rate' => 0,
    'min_grade' => 0,
    'max_grade' => 0,
    'avg_grade' => 0
);

if (!empty($students)) {
    $grades = array();
    $passed_count = 0;
    $total_grades = 0;
    
    foreach ($students as $student) {
        $grade = $student['calculated_grade'];
        $grades[] = $grade;
        $total_grades += $grade;
        
        if ($grade >= 12) {
            $passed_count++;
        }
    }
    
    $statistics['passed_students'] = $passed_count;
    $statistics['failed_students'] = $statistics['total_students'] - $statistics['passed_students'];
    $statistics['pass_rate'] = ($statistics['total_students'] > 0) ? 
        round(($statistics['passed_students'] / $statistics['total_students']) * 100, 1) : 0;
    $statistics['min_grade'] = round(min($grades), 2);
    $statistics['max_grade'] = round(max($grades), 2);
    $statistics['avg_grade'] = round($total_grades / count($grades), 2);
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
  </head>
  <body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="#">
          <img src="image/ensao.png" alt="ENSAO Logo" />
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
              <a class="nav-link active" href="dashboard.php"
                ><i class="fas fa-home active"></i> Tableau de bord</a
              >
            </li>
            <li class="nav-item">
              <a class="nav-link" href="notes.php"
                ><i class="fas fa-chart-bar"></i> Notes</a
              >
            </li>
          </ul>
          <div class="user-profile">
            <img src="image/Avatar.png" alt="Avatar Enseignant" />
            <span><?php echo htmlspecialchars($teacher['full_name']); ?> <br> <a class="logout" href="login.html">Logout</a></span>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
      <div class="container">
        <!-- Welcome Card -->
        <div class="welcome-card">
          <h2>Gestion des Étudiants</h2>
          <p>
            Consultez les informations de vos étudiants, leurs moyennes
            générales et leur statut de réussite pour chaque classe que vous
            enseignez.
          </p>
        </div>

        <div class="row">
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
                    <h5><?php echo $teacher_stats['total_classes']; ?></h5>
                    <p>Classes</p>
                  </div>
                  <div class="info-item">
                    <h5><?php echo $teacher_stats['total_modules']; ?></h5>
                    <p>Modules</p>
                  </div>
                  <div class="info-item">
                    <h5><?php echo $teacher_stats['total_students']; ?></h5>
                    <p>Étudiants</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Class Statistics -->
            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">Statistiques de la classe</h5>
              </div>
              <div class="card-body">
                <div
                  class="d-flex justify-content-between align-items-center mb-3"
                >
                  <span>Nombre total d'étudiants:</span>
                  <strong class="text-primary"><?php echo $statistics['total_students']; ?></strong>
                </div>
                <div
                  class="d-flex justify-content-between align-items-center mb-3"
                >
                  <span>Étudiants validés:</span>
                  <strong class="text-success"><?php echo $statistics['passed_students']; ?></strong>
                </div>
                <div
                  class="d-flex justify-content-between align-items-center mb-3"
                >
                  <span>Étudiants non validés:</span>
                  <strong class="text-danger"><?php echo $statistics['failed_students']; ?></strong>
                </div>
                <div
                  class="d-flex justify-content-between align-items-center mb-3"
                >
                  <span>Taux de réussite:</span>
                  <strong class="text-success"><?php echo $statistics['pass_rate']; ?>%</strong>
                </div>
                <div class="progress mb-3" style="height: 10px">
                  <div
                    class="progress-bar bg-success"
                    role="progressbar"
                    style="width: <?php echo $statistics['pass_rate']; ?>%"
                    aria-valuenow="<?php echo $statistics['pass_rate']; ?>"
                    aria-valuemin="0"
                    aria-valuemax="100"
                  ></div>
                </div>
                <button class="btn btn-primary btn-sm w-100">
                  Exporter la liste
                </button>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <!-- Students Management Section -->
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="mb-0">Liste des Étudiants</h5>
              </div>
              <div class="card-body">
                <form method="GET" action="">
                  <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($teacher_id); ?>">
                  <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                      <label for="academic_year" class="form-label"
                        >Année universitaire:</label
                      >
                      <select
                        class="form-select"
                        id="academic_year"
                        name="academic_year"
                        onchange="this.form.submit()"
                      >
                        <option value="2024/2025" <?php echo $academic_year == '2024/2025' ? 'selected' : ''; ?>>2024/2025</option>
                        <option value="2023/2024" <?php echo $academic_year == '2023/2024' ? 'selected' : ''; ?>>2023/2024</option>
                        <option value="2022/2023" <?php echo $academic_year == '2022/2023' ? 'selected' : ''; ?>>2022/2023</option>
                      </select>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label for="semester" class="form-label">Semestre:</label>
                      <select
                        class="form-select"
                        id="semester"
                        name="semester"
                        onchange="this.form.submit()"
                      >
                        <option value="S1" <?php echo $semester == 'S1' ? 'selected' : ''; ?>>S1</option>
                        <option value="S2" <?php echo $semester == 'S2' ? 'selected' : ''; ?>>S2</option>
                      </select>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label for="module" class="form-label">Module:</label>
                      <select
                        class="form-select"
                        id="module"
                        name="module"
                        onchange="this.form.submit()"
                      >
                        <?php foreach ($modules as $mod): ?>
                          <option value="<?php echo htmlspecialchars($mod['module_code']); ?>" 
                                  <?php echo $module == $mod['module_code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mod['module_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </form>
                <div class="table-responsive">
                  <table
                    class="table table-hover students-table"
                    id="studentsTable"
                  >
                    <thead class="table-light">
                      <tr>
                        <th>Matricule</th>
                        <th>Nom complet</th>
                        <th>Moyenne générale</th>
                        <th>Statut</th>
                      </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                      <?php if (empty($students)): ?>
                        <tr>
                          <td colspan="4" class="empty-students-row">
                            <i class="fas fa-users fa-3x mb-3 text-muted"></i><br>
                            Aucun étudiant trouvé pour cette sélection
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($students as $student): ?>
                          <?php 
                            $grade = round($student['calculated_grade'], 2);
                            $status = $grade >= 12 ? 'V' : 'NV';
                            $statusClass = $status == 'V' ? 'status-pass' : 'status-fail';
                          ?>
                          <tr>
                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                            <td><?php echo $grade; ?></td>
                            <td><span class="<?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                          </tr>
                        <?php endforeach; ?>
                        
                        <!-- Statistics rows -->
                        <tr class="class-stats">
                          <td><strong>Note min</strong></td>
                          <td>-</td>
                          <td><strong><?php echo $statistics['min_grade']; ?></strong></td>
                          <td>-</td>
                        </tr>
                        <tr class="class-stats">
                          <td><strong>Note max</strong></td>
                          <td>-</td>
                          <td><strong><?php echo $statistics['max_grade']; ?></strong></td>
                          <td>-</td>
                        </tr>
                        <tr class="class-stats">
                          <td><strong>Moyenne</strong></td>
                          <td>-</td>
                          <td><strong><?php echo $statistics['avg_grade']; ?></strong></td>
                          <td>-</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  </body>
</html>

<?php
// Close database connection
mysqli_close($connection);
?>