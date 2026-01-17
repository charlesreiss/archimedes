<?php
// accept asynchronous grade postings, comment set updates, and comment set queries without HTML content

include "tools.php";
$noPre = true; // turn off <pre></pre> stuff
logInAs();
if (!$isstaff) die("page restricted to staff");

if (array_key_exists('assignment', $_REQUEST)) {
    $assignment = $_REQUEST['assignment'];
?>
<!DOCTYPE html>
<html><head>
    <title><?=$metadata['title']?> Grading Statistics for <?=htmlspecialchars($assignment)?></title>
</head>
<body>
<h1>Grading statistics by grader for <?=htmlspecialchars($assignment)?></h1>
<p>Note: this counts the number of unique students graded based on the grading log.
If a student's submission is graded, but the grade is invalidated because they resubmit,
that is still counted.
Also, if two or more graders have
submitted grades for a student's submission, that is counted multiple times.
<ul>
<?php
    $assignments = assignments();
    if (!array_key_exists($assignment, $assignments)) {
        echo("No such assignment");
    }
    header('Content-Type: text/html; charset=utf-8');
    $history = get_grade_history($assignment);
    foreach ($history["by_grader"] as $grader => $lst) {
        $graded_full = 0;
        $graded_partial = 0;
        $earliest = time();
        $latest = 0;
        foreach ($lst as $student => $student_lst) {
            $any_complete = false;
            foreach ($student_lst as $grade) {
                if (!$grade['incomplete']) {
                    $any_complete = true;
                }
                $latest = max($grade['timestamp'], $latest);
                $earliest = min($grade['timestamp'], $earliest);
            }
            if ($any_complete) {
                $graded_full += 1;
            } else {
                $graded_partial += 1;
            }
        }
        echo "<li>$grader: $graded_full graded (+ $graded_partial partial) between ".
            date_format(date_create("@".$earliest), "Y-m-d h:i")." and ".
            date_format(date_create("@".$latest), "Y-m-d h:i")."</li>\n";
    }
}
?>
</ul>
</body>
</html>
