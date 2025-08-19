<?php
session_start();
include '../config/dbConnect.php';
// Only allow logged-in users with appropriate role (adjust as needed)
if (!isset($_SESSION['userID']) || !isset($_SESSION['roleID'])) {
    header("Location: ../index.php?error=access_denied");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Job Allowance Breakdown</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="../assets/img/logo_white.png" type="image/x-icon" />
    <!-- Fonts and icons -->
    <script src="../assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
      WebFont.load({
        google: { families: ["Public Sans:300,400,500,600,700"] },
        custom: {
          families: [
            "Font Awesome 5 Solid",
            "Font Awesome 5 Regular",
            "Font Awesome 5 Brands",
            "simple-line-icons",
          ],
          urls: ["../assets/css/fonts.min.css"],
        },
        active: function () {
          sessionStorage.fonts = true;
        },
      });
    </script>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/plugins.min.css" />
    <link rel="stylesheet" href="../assets/css/kaiadmin.min.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <!-- SheetJS for XLSX/CSV -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <!-- jsPDF for PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- FileSaver for DOCX/Word export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
</head>
<body>
<div class="wrapper">
    <?php include 'components/adminSidebar.php'; ?>
    <div class="main-panel">
        <div class="main-header">
            <div class="main-header-logo">
                <div class="logo-header" data-background-color="dark">
                    <a href="../index.html" class="logo">
                        <img src="../assets/img/Logo_white.png" alt="navbar brand" class="navbar-brand" height="20" />
                    </a>
                </div>
            </div>
            <?php include 'components/navbar.php'; ?>
        </div>
        <div class="container">
            <div class="page-inner">
                <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                    <div>
                        <h3 class="fw-bold mb-3">Job Allowance Breakdown</h3>
                        <h6 class="op-7 mb-2">View allowance arrangement for employees assigned to a job</h6>
                    </div>
                </div>
                <form id="breakdownForm" class="row g-3 mb-4 align-items-end">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month</label>
                        <select class="form-select" id="month" name="month" required>
                            <option value="">Select Month</option>
                            <?php
                            $months = [
                                '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                                '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                                '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                            ];
                            foreach ($months as $num => $name) {
                                echo "<option value='$num'>$name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-select" id="year" name="year" required>
                            <option value="">Select Year</option>
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                echo "<option value='$y'>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="jobID" class="form-label">Job</label>
                        <select class="form-select" id="jobID" name="jobID" required>
                            <option value="">Select Job</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Show Breakdown</button>
                        <button type="button" id="showMonthSummary" class="btn btn-outline-secondary btn-sm w-100 d-none">Advanced Search</button>
                    </div>
                </form>

                <!-- Driver-wise Search Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Driver-wise Search</h5>
                    </div>
                    <div class="card-body">
                        <form id="driverBreakdownForm" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="driverMonth" class="form-label">Month</label>
                                <select class="form-select" id="driverMonth" name="driverMonth" required>
                                    <option value="">Select Month</option>
                                    <?php
                                    foreach ($months as $num => $name) {
                                        echo "<option value='$num'>$name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="driverYear" class="form-label">Year</label>
                                <select class="form-select" id="driverYear" name="driverYear" required>
                                    <option value="">Select Year</option>
                                    <?php
                                    for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                        echo "<option value='$y'>$y</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="driverID" class="form-label">Driver</label>
                                <select class="form-select" id="driverID" name="driverID" required>
                                    <option value="">Select Driver</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-success btn-sm w-100">Show Driver Breakdown</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div id="breakdownResult">
                    <!-- Allowance breakdown table will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load jobs when month/year changes
    $('#month, #year').change(function() {
        var month = $('#month').val();
        var year = $('#year').val();
        if (month && year) {
            $.ajax({
                url: '../controllers/getJobAllowanceBreakdownController.php',
                type: 'POST',
                data: { action: 'getJobs', month: month, year: year },
                success: function(response) {
                    $('#jobID').html(response);
                }
            });
        } else {
            $('#jobID').html('<option value="">Select Job</option>');
        }
        // Show/hide Advanced Search button
        if (month && year && (!$('#jobID').val() || $('#jobID').val() === '')) {
            $('#showMonthSummary').removeClass('d-none');
        } else {
            $('#showMonthSummary').addClass('d-none');
        }
    });
    // Also show/hide Advanced Search button when jobID changes
    $('#jobID').change(function() {
        var month = $('#month').val();
        var year = $('#year').val();
        if (month && year && (!$('#jobID').val() || $('#jobID').val() === '')) {
            $('#showMonthSummary').removeClass('d-none');
        } else {
            $('#showMonthSummary').addClass('d-none');
        }
    });
    // Show breakdown on form submit
    $('#breakdownForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '../controllers/getJobAllowanceBreakdownController.php',
            type: 'POST',
            data: $(this).serialize() + '&action=getBreakdown',
            success: function(response) {
                $('#breakdownResult').html(response);
                // Add export buttons if a table exists
                if ($('#breakdownResult table').length) {
                    $('#breakdownResult').append(`
                        <div class="mt-3 mb-2 text-end" id="exportButtons">
                            <button class="btn btn-outline-primary btn-sm me-1" onclick="exportTable('csv')">Export CSV</button>
                            <button class="btn btn-outline-success btn-sm me-1" onclick="exportTable('xlsx')">Export XLSX</button>
                            <button class="btn btn-outline-info btn-sm me-1" onclick="exportTable('docx')">Export DOCX</button>
                            <button class="btn btn-outline-danger btn-sm" onclick="exportTable('pdf')">Export PDF</button>
                        </div>
                    `);
                }
            }
        });
    });
    // Advanced Search: Month Summary
    $('#showMonthSummary').click(function() {
        var month = $('#month').val();
        var year = $('#year').val();
        if (month && year) {
            $('#breakdownResult').html('<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Loading summary...</div>');
            $.ajax({
                url: '../controllers/getJobAllowanceBreakdownController.php',
                type: 'POST',
                data: { action: 'getMonthSummary', month: month, year: year },
                success: function(response) {
                    $('#breakdownResult').html(response);
                    // Add export buttons if a table exists
                    if ($('#breakdownResult table').length) {
                        $('#breakdownResult').append(`
                            <div class="mt-3 mb-2 text-end" id="exportButtons">
                                <button class="btn btn-outline-primary btn-sm me-1" onclick="exportTable('csv')">Export CSV</button>
                                <button class="btn btn-outline-success btn-sm me-1" onclick="exportTable('xlsx')">Export XLSX</button>
                                <button class="btn btn-outline-info btn-sm me-1" onclick="exportTable('docx')">Export DOCX</button>
                                <button class="btn btn-outline-danger btn-sm" onclick="exportTable('pdf')">Export PDF</button>
                            </div>
                        `);
                    }
                }
            });
        }
    });

    // Driver-wise search functionality
    $('#driverMonth, #driverYear').change(function() {
        var month = $('#driverMonth').val();
        var year = $('#driverYear').val();
        if (month && year) {
            $.ajax({
                url: '../controllers/getJobAllowanceBreakdownController.php',
                type: 'POST',
                data: { action: 'getDrivers', month: month, year: year },
                success: function(response) {
                    $('#driverID').html(response);
                }
            });
        } else {
            $('#driverID').html('<option value="">Select Driver</option>');
        }
    });

    // Driver breakdown form submit
    $('#driverBreakdownForm').submit(function(e) {
        e.preventDefault();
        $('#breakdownResult').html('<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Loading driver breakdown...</div>');
        $.ajax({
            url: '../controllers/getJobAllowanceBreakdownController.php',
            type: 'POST',
            data: $(this).serialize() + '&action=getDriverBreakdown',
            success: function(response) {
                $('#breakdownResult').html(response);
                // Add export buttons if a table exists
                if ($('#breakdownResult table').length) {
                    $('#breakdownResult').append(`
                        <div class="mt-3 mb-2 text-end" id="exportButtons">
                            <button class="btn btn-outline-primary btn-sm me-1" onclick="exportTable('csv')">Export CSV</button>
                            <button class="btn btn-outline-success btn-sm me-1" onclick="exportTable('xlsx')">Export XLSX</button>
                            <button class="btn btn-outline-info btn-sm me-1" onclick="exportTable('docx')">Export DOCX</button>
                            <button class="btn btn-outline-danger btn-sm" onclick="exportTable('pdf')">Export PDF</button>
                        </div>
                    `);
                }
            }
        });
    });
});
</script>
<script>
window.exportTable = function(type) {
    var table = document.querySelector('#breakdownResult table');
    if (!table) return alert('No table to export!');

    if (type === 'csv' || type === 'xlsx') {
        // Use SheetJS
        var wb = XLSX.utils.table_to_book(table, {sheet:"Breakdown"});
        var ext = type === 'csv' ? 'csv' : 'xlsx';
        XLSX.writeFile(wb, 'breakdown.' + ext, {bookType: ext});
    } else if (type === 'pdf') {
        // Use jsPDF + html2canvas
        var doc = new jspdf.jsPDF('l', 'pt', 'a4');
        html2canvas(table).then(function(canvas) {
            var imgData = canvas.toDataURL('image/png');
            var pageWidth = doc.internal.pageSize.getWidth();
            var pageHeight = doc.internal.pageSize.getHeight();
            var imgWidth = pageWidth - 40;
            var imgHeight = canvas.height * imgWidth / canvas.width;
            doc.addImage(imgData, 'PNG', 20, 20, imgWidth, imgHeight);
            doc.save('breakdown.pdf');
        });
    } else if (type === 'docx') {
        // Simple HTML to Word export
        var html = table.outerHTML;
        var blob = new Blob(['\ufeff'+html], {type: 'application/msword'});
        saveAs(blob, 'breakdown.doc');
    }
}
</script>
<!--   Core JS Files   -->
<script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- Chart JS -->
    <script src="../assets/js/plugin/chart.js/chart.min.js"></script>

    <!-- jQuery Sparkline -->
    <script src="../assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>

    <!-- Chart Circle -->
    <script src="../assets/js/plugin/chart-circle/circles.min.js"></script>

    <!-- Datatables -->
    <script src="../assets/js/plugin/datatables/datatables.min.js"></script>

    <!-- Bootstrap Notify -->
    <script src="../assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js"></script>

    <!-- jQuery Vector Maps -->
    <script src="../assets/js/plugin/jsvectormap/jsvectormap.min.js"></script>
    <script src="../assets/js/plugin/jsvectormap/world.js"></script>

    <!-- Sweet Alert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Kaiadmin JS -->
    <script src="../assets/js/kaiadmin.min.js"></script>
</body>
</html>
