<?php
/******************************************************************************
 * Spalten einer CSV-Datei werden Datenbankfeldern zugeordnet
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

$err_code = "";
$err_text = "";

// nur berechtigte User duerfen User importieren
if(!$g_current_user->editUser())
{
    $g_message->show("norights");
}

if(count($_SESSION['file_lines']) == 0)
{
    $g_message->show("file_not_exist");
}

// feststellen, welches Trennzeichen in der Datei verwendet wurde
$count_comma     = 0;
$count_semicolon = 0;
$count_tabulator = 0;

$line = reset($_SESSION["file_lines"]);
for($i = 0; $i < count($_SESSION["file_lines"]); $i++)
{
    $count = substr_count($line, ",");
    $count_comma += $count;
    $count = substr_count($line, ";");
    $count_semicolon += $count;
    $count = substr_count($line, "\t");
    $count_tabulator += $count;

    $line = next($_SESSION["file_lines"]);
}

if($count_semicolon > $count_comma && $count_semicolon > $count_tabulator)
{
    $_SESSION["value_separator"] = ";";
}
elseif($count_tabulator > $count_semicolon && $count_tabulator > $count_comma)
{
    $_SESSION["value_separator"] = "\t";
}
else
{
    $_SESSION["value_separator"] = ",";
}

// Html-Kopf ausgeben
$g_layout['title'] = "Benutzer importieren";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"import_csv.php\" method=\"post\">
    <div class=\"formHead\">Felder zuordnen</div>
    <div class=\"formBody\">
        <div style=\"text-align: center; width: 100%;\">
            <p>Ordne den Datenbankfeldern, wenn m&ouml;glich eine Spalte aus der Datei zu.</p>
            <p>Auf der linken Seite stehen alle m&ouml;glichen Datenbankfelder und auf der
            rechten Seite sind jeweils alle Spalten aus der ausgew&auml;hlten Datei
            aufgelistet. Falls nicht alle Datenbankfelder in der Datei vorhanden sind, k&ouml;nnen
            diese Felder einfach leer gelassen werden.</p>
        </div>

        <div style=\"margin-top: 6px; margin-bottom: 10px;\">
            <input type=\"checkbox\" id=\"first_row\" name=\"first_row\" style=\"vertical-align: middle;\" checked value=\"1\" />&nbsp;
            <label for=\"first_row\">Erste Zeile beinhaltet die Spaltenbezeichnungen</label>
        </div>

        <table class=\"tableList\" style=\"width: 80%;\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>
                <th class=\"tableHeader\">Datenbankfeld</th>
                <th class=\"tableHeader\">Dateispalte</th>
            </tr>";

            // Array mit allen User-Feldern, die importiert werden koennen
            $arr_col_name = array('usr_last_name'  => 'Nachname',
                                  'usr_first_name' => 'Vorname',
                                  'usr_address'    => 'Adresse',
                                  'usr_zip_code'   => 'PLZ',
                                  'usr_city'       => 'Ort',
                                  'usr_country'    => 'Land',
                                  'usr_phone'      => 'Telefon',
                                  'usr_mobile'     => 'Handy',
                                  'usr_fax'        => 'Fax',
                                  'usr_email'      => 'E-Mail',
                                  'usr_homepage'   => 'Homepage',
                                  'usr_birthday'   => 'Geburtstag',
                                  'usr_gender'     => 'Geschlecht',
            );

            // Organisationsspezifische Felder noch in das Array aufnehmen
            $sql = "SELECT *
                      FROM ". TBL_USER_FIELDS. "
                     WHERE (  usf_org_id = $g_current_organization->id
                           OR usf_org_id IS NULL )
                     ORDER BY usf_org_id DESC, usf_name ASC ";
            $result_field = mysql_query($sql, $g_adm_con);
            db_error($result_field,__FILE__,__LINE__);

            while($row = mysql_fetch_object($result_field))
            {
                $arr_col_name[$row->usf_id] = $row->usf_name;
            }

            $line = reset($_SESSION["file_lines"]);
            $arr_columns = explode($_SESSION["value_separator"], $line);

            // jedes Benutzerfeld aus der Datenbank auflisten
            $db_column = reset($arr_col_name);
            for($i = 0; $i < count($arr_col_name); $i++)
            {
                echo "<tr>
                    <td style=\"text-align: center;\">$db_column</td>
                    <td style=\"text-align: center;\">
                        <select size=\"1\" id=\"". key($arr_col_name). "\" name=\"". key($arr_col_name). "\">
                            <option value=\"0\" selected=\"selected\"></option>";

                            // Alle Spalten aus der Datei in Combobox auflisten
                            $column = reset($arr_columns);
                            for($j = 1; $j <= count($arr_columns); $j++)
                            {
                                $column = trim($column);
                                $column = str_replace("\"", "", $column);
                                echo "<option value=\"$j\">$column</option>";
                                $column = next($arr_columns);
                            }
                            reset($arr_columns);
                        echo "</select>";
                        // Nachname und Vorname als Pflichtfelder kennzeichnen
                        if(key($arr_col_name) == "usr_last_name" || key($arr_col_name) == "usr_first_name")
                        {
                            echo "&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>";
                        }
                        else
                        {
                            echo "&nbsp;&nbsp;&nbsp;";
                        }
                    echo "</td>
                </tr>";
                $db_column = next($arr_col_name);
            }
        echo "</table>

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id=\"weiter\" type=\"submit\" value=\"weiter\" tabindex=\"2\">Weiter&nbsp;
            <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Weiter\"></button>
        </div>
    </div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('first_row').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>