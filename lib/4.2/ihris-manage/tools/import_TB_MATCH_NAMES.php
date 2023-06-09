#!/usr/bin/php
<?php
/*
 * © Copyright 2007, 2008 IntraHealth International, Inc.
 * 
 * This File is part of iHRIS
 * 
 * iHRIS is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * The page wrangler
 * 
 * This page loads the main HTML template for the home page of the site.
 * @package iHRIS
 * @subpackage DemoManage
 * @access public
 * @author Carl Leitner <litlfred@ibiblio.org>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @since Demo-v2.a
 * @version Demo-v2.a
 */


require_once("./import_base.php");



/*********************************************
*
*      Process Class
*
*********************************************/

class TB_MATCH_NAMES extends Processor {
    protected $course_ids;
    public function __construct($file) {        
        parent::__construct($file);
    }

    protected function getExpectedHeaders() {
        return  array(
            'id'=>'ID',
            'surname'=>'Last Name',
            'firstname'=>'First Name',
            'middlename'=>'Middle Name',
            'start'=>'Date',
            'training_course'=>'Training Name',
            'district'=>'District',
            'qualification'=>'Qualification (primary)'
            );
    }

protected $district_ids = array(
    'district|1'=> 'BOBIRWA',
    'district|2'=> 'BOTETI',
    'district|24'=> 'CHARLESHILL SUB-DISTRICT',
    'district|3'=> 'CHOBE',
    'district|4'=> 'FRANCISTOWN',
    'district|5'=> 'GABORONE',
    'district|6'=> 'GANTSI',
    'district|7'=> 'GOODHOPE',
    'district|23'=> 'JWANENG',
    'district|8'=> 'KGALAGADI NORTH',
    'district|9'=> 'KGALAGADI SOUTH',
    'district|10'=> 'KGATLENG',
    'district|11'=> 'KWENENG EAST',
    'district|28'=> 'KWENENG WEST',
    'district|12'=> 'LOBATSE',
    'district|26'=> 'MABUTSANE SUB-DISTRICT',
    'district|13'=> 'MAHALAPYE',
    'district|14'=> 'MOSHUPA SUB-DISTRICT',
    'district|15'=> 'NGAMILAND',
    'district|16'=> 'NORTH EAST',
    'district|17'=> 'OKAVANGO',
    'district|27'=> 'SELIBE PHIKWE',
    'district|18'=> 'SEROWE',
    'district|19'=> 'SOUTH EAST',
    'district|20'=> 'SOUTHERN',
    'district|22'=> 'TLOKWENG SUB-DISTRICT',
    'district|25'=> 'TONOTA SUB-DISTRICT',
    'district|21'=> 'TUTUME',
    'district|31'=> 'PALAPYE'
);    


    protected function _processRow() {
        $this->process_stats_checked = array();
        if ( ($personId = $this->findPersonByID($this->mapped_data['surname'], $this->mapped_data['firstname'],$this->mapped_data['middlename'])) === false) {
            $this->processStats('bad_person');
            return false;
        }
        if ( ($sCourseID = $this->findScheduledCourse($this->mapped_data['start'],$this->mapped_data['district'])) === false) {
            $this->processStats('bad_course');
            return false;
        }
        
        
        if ( ( $psCourseID = $this->assignToCourse($personId,$sCourseID)) === false) {
            $this->addBadRecord('Could not link person to course');
            $this->processStats('bad_course_link');
            return false;
        }
        
        return true;
    }

    function convertDate($date) {
        $parts = array_pad(explode("/",$date),3,'');
        foreach ($parts as $part) {
            if (strlen($part) == 0 || strlen($part) > 4) {                
                return false;
            }
            if (strlen($part) == 1) {
                $part = '0'. $part;
            }
        }
        list($m,$d,$y) = $parts;
        if ($m < 1 || $m > 12 || $d < 1 || $d>31 || $y < 1 || $y > 2013) {
            return false;
        }
        //converss "04/23/20130"  to "2010-04-23 00:00:00"
        return $y . '-' . $m . '-'. $d . ' 00:00:00';
    }

    function findScheduledCourse($start, $venue) {
        $venue = strtolower(trim($venue));
        $course = 'KITSO64';
        $start = $this->convertDate($start);
        if (!$start) {
            $this->addBadRecord("Invalid start date, can't create this");
            return false;
        }
        
        $where = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'start_date',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$start
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'end_date',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$start
                        )
                    ),
                2=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'training_course',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>'training_course|'.$course
                        )
                    )
                )
            );
        
        $sCourse_ids = I2CE_FormStorage::search('scheduled_training_course',false,$where);
        
        if (count($sCourse_ids) > 1) {
            $this->processStats('duplicate_scheduled_course');
            print_r($sCourse_ids);
            return false;
        }
        elseif (count($sCourse_ids) == 1) {
            //$this->addBadRecord("scheduled course found");
            //$sCourseObj = $this->ff->createContainer( 'scheduled_training_course'
            return current($sCourse_ids);
        } 
        elseif( count($sCourse_ids) == 0 ){
          if ( !($sCourseObj = $this->ff->createContainer( 'scheduled_training_course')) instanceof iHRIS_Scheduled_Training_Course) {
            $this->addBadRecord("failed initialization");
              $this->processStats('cannot_create_stc');
              return false;
          }else{
          //$this->addBadRecord("scheduled course not found");
          $sCourseObj->getField('start_date')->setFromDB($start);
          $sCourseObj->getField('end_date')->setFromDB($start);
          //$sCourseObj->getField('venue')->setValue($this->mapped_data['venue']);
          $sCourseObj->getField('training_course')->setValue(array('training_course',$course));
          $sCourseID = $this->save($sCourseObj);
          $sCourseObj->cleanup();
          echo "returning course id as $sCourseID\n";
          return $sCourseID;
          }
        }
    }
    
    function assignToCourse($personID,$sCourseID) {
        if (! ($psCourseObj = $this->ff->createContainer( 'person_scheduled_training_course'))instanceof iHRIS_Person_Scheduled_Training_Course) {
            $this->processStats('cannot_create_pstc');
            return false;
        }
        $where_pstc = array(
            'operator'=>'AND',
            'operand'=>array(
                0=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'parent',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>$personID
                        )
                    ),
                1=>array(
                    'operator'=>'FIELD_LIMIT',
                    'field'=>'scheduled_training_course',
                    'style'=>'equals',
                    'data'=>array(
                        'value'=>'scheduled_training_course|'.$sCourseID
                        )
                    )
                )
            );
        $hasAttendedThisCourse = I2CE_FormStorage::listFields('person_scheduled_training_course',array('id', 'scheduled_training_course', 'parent'), false,$where_pstc);
        echo "checked attended course\n";
        if( count($hasAttendedThisCourse) > 1 ){
            $this->addBadRecord("This person is assigned more than once to this course ");
            return false;
          }
        if( count($hasAttendedThisCourse) == 1 ){
            $data = current($hasAttendedThisCourse);
            //$this->addBadRecord("has attended this course");
            return "person_scheduled_training_course|" . $data['id'];
          }
        else{
          //$this->addBadRecord("assigning to course");
          $psCourseObj->setParent($personID);
          $psCourseObj->getField('scheduled_training_course')->setValue(array('scheduled_training_course',$sCourseID));
          $psCourseID = "person_scheduled_training_course|" . $this->save($psCourseObj);
          $psCourseObj->cleanup();
          return $psCourseID;
        }
    }
    
    protected function processStats($stat) {
        //echo "Stat:$stat\n";
        if (!array_key_exists($stat,$this->process_stats)) {
            $this->process_stats[$stat] = 0;
        }
        if (in_array($stat,$this->process_stats_checked)) {
            return;
        }
        $this->process_stats[$stat]++;

    }
    
    protected $process_stats = array();
    protected $process_stats_checked = array();

    protected $duplicate_ids = array();

    function findPersonByID($surname, $firstname, $middle_name) {
			$lname = strtolower(trim($surname));
			$fname = strtolower(trim($firstname));
			$where_names = array(
				'operator'=>'AND',
				'operand'=>array(
					0=>array(
						'operator'=>'FIELD_LIMIT',
						'field'=>'surname',
						'style'=>'lowerequals',
						'data'=>array(
							'value'=>$lname
							)
						),
					1=>array(
						'operator'=>'FIELD_LIMIT',
						'field'=>'firstname',
						'style'=>'lowerequals',
						'data'=>array(
							'value'=>$fname
							)
						)
					)
				);
			$personIDs = I2CE_FormStorage::listFields('person',array('id'),false,$where_names);
			
			if ( count($personIDs) > 1 ){
				$this->addBadRecord("this name exists in duplicates");
				return false;
				}
			elseif ( count($personIDs) == 1 ){
				$person_id = 'person|'.current($personIDs);
				$personObjId = current($personIDs);
				return 'person|'.$personObjId['id'];
			}
        
        
        if (count($personIDs) == 0) {
          if( strlen($fname) == 1 || strpos($fname, '.') === true ){
              $this->addBadRecord("confirm the first name by hand");
              return false;
            }
            if (! ($personObj = $this->ff->createContainer( 'person')) instanceof iHRIS_Person) {
              echo "failed initialization\n";
                $this->processStats('cannot_create_person');
                return false;
            }
            
            $personObj->getField('surname')->setFromDB(trim($surname));
            $personObj->getField('firstname')->setFromDB(trim($firstname));
            $personObj->getField('othername')->setFromDB(trim($middle_name));
            $personID = 'person|' . $this->save($personObj);
            $personObj->cleanup();
            
            $pRecordStatus = $this->ff->createContainer( 'person_recordstatus');
            $pRecordStatus->getField('incomplete')->setFromDB(1);
            $pRecordStatus->getField('duplicate')->setFromDB(0);
            $pRecordStatus->getField('incorrect')->setFromDB(0);
            $pRecordStatus->getField('comment')->setFromDB('Needs Review');
            $pRecordStatus->setParent( $personID );
            $this->save($pRecordStatus);
            $pRecordStatus->cleanup();
          return $personID;
        }
      }
  }




/*********************************************
*
*      Execute!
*
*********************************************/

//ini_set('memory_limit','4G');

if (count($arg_files) != 1) {
    usage("Please specify the name of a spreadsheet to process");
}

reset($arg_files);
$file = current($arg_files);
if($file[0] == '/') {
    $file = realpath($file);
} else {
    $file = realpath($dir. '/' . $file);
}
if (!is_readable($file)) {
    usage("Please specify the name of a spreadsheet to import: " . $file . " is not readable");
}

I2CE::raiseMessage("Loading from $file");


$processor = new TB_MATCH_NAMES($file);
$processor->run();

echo "Processing Statistics:\n";
print_r( $processor->getStats());




# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
