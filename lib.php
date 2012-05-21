<?php

/**
 * Extradata Library
 *
 * @package   localcal
 * @copyright Xenu and Megalodon
 * @author    Mark Nelson <mark@moodle.com.au>, Pukunui Technology
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This function adds the extra data
 * @global $DB
 * @param array $prevdata the previous data submitted, if there was any
 * @return string the html for pages to display
 */
function local_extradata_get_additional_file_upload_info($prevdata = null) {
    global $DB;

    if (empty($prevdata)) {
        $table = new html_table();
        $table->width = '100%';
        $table->align = array('left', 'left');

        // New row
        $row = new html_table_row();

        $cell = new html_table_cell();
        $cell->text .= "<input type='hidden' name='currentpage' value='1' />";
        $cell->text .= "Is this a file of Xenu? It better be.";
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = "<select name='isxenu'>";
        $cell->text .= "<option value='1'>Yes</option>";
        $cell->text .= "<option value='0'>No</option>";
        $cell->text .= "</select>";
        $row->cells[] = $cell;

        $table->data[] = $row;

        return html_writer::table($table);
    } else if ($prevdata->currentpage == 1) {
        // Update with the answer
        $extradata = new stdClass();
        $extradata->id = $prevdata->extradataid;
        $extradata->isxenu = $prevdata->isxenu;
        $DB->update_record('extradata_fileinfo', $extradata);

        $html = "<input type='hidden' name='currentpage' value='2' />";
        $html .= "You completed the survey! Congratulations, you just wasted seconds of your life";

        return $html;
    } else if ($prevdata->currentpage == 2) {
        // Returning false will close the window
        return false;
    }

    return false;
}

/**
 * This function is passed the finalised file information,
 * so that it is able to use that information as it pleases
 * @global $DB
 * @param array $data the file information
 */
function local_extradata_process_file_upload_info($data) {
    global $DB, $USER;

    // Check if the file exists already via content hash
    $draftfile = $DB->get_record('files', array('itemid' => $data['id'],
        'filename' => $data['file']));
    $contenthash = $draftfile->contenthash;

    // Save the draft file that was just uploaded
    $extradata = new stdClass();
    $extradata->userid = $USER->id;
    $extradata->fileid = '0';
    $extradata->filename = $data['file'];
    $extradata->contenthash = $contenthash;
    $extradata->timecreated = time();

    // Insert into the database
    $extradata->id = $DB->insert_record('extradata_fileinfo', $extradata);

    // You can add more information to pass back if you want to use it for further
    $data['additional_variables'] = array('extradataid' => $extradata->id);
    return $data;
}

/**
 * The local cal cron job, handles
 * sending emails etc.
 */
function local_extradata_cron() {
    global $DB;

    $time = time();

    // Loop through the files that have a fileid of 0, and get their file id
    if ($files = $DB->get_records('extradata_fileinfo', array('fileid'=>0), 'id ASC')) {
        foreach ($files as $f) {
            // Get the max fileid in the extradata_fileinfo table, so we do not reference any
            // files before this point that have already been referenced
            $sql = "SELECT MAX(fileid) as maxfileid
                    FROM {extradata_fileinfo}";
            $maxfileid = $DB->get_record_sql($sql);
            $maxfileid = $maxfileid->maxfileid;
            // Check if it exists in the files table (not as a draft), and is not yet
            // stored in the extradata_fileinfo table (greater than the max fileid recorded so far)
            $sql = "SELECT *
                    FROM {files}
                    WHERE contenthash = :contenthash
                    AND filearea != 'draft'
                    AND filename != '.'
                    AND id > :maxfileid
                    ORDER BY id ASC";
            if ($mdlfiles = $DB->get_records_sql($sql, array('contenthash'=>$f->contenthash, 'maxfileid'=>$maxfileid), 0, 1)) {
                // Loop through the Moodle files
                foreach ($mdlfiles as $mf) {
                    $f->fileid = $mf->id;
                    $DB->update_record('extradata_fileinfo', $f);
                    break;
                }
            }
        }
    }

    // Loop through and delete any files that were not given
    // a valid fileid and older than a day old
    $sql = "SELECT *
            FROM {extradata_fileinfo}
            WHERE fileid = '0'
            AND timecreated <= :time";
    if ($files = $DB->get_records_sql($sql, array('time'=>$time-86400))) {
        // Loop through and delete
        foreach ($files as $f) {
            // Delete the file record
            $DB->delete_records('extradata_fileinfo', array('id'=>$f->id));
        }
    }
}

?>
