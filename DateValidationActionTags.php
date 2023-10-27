<?php
/**
 * REDCap External Module: DateValidationActionTags
 * Action tags to validate date and date time entries as @FUTURE, @NOTPAST, @PAST, @NOTFUTURE.
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
namespace MCRI\DateValidationActionTags;

use ExternalModules\AbstractExternalModule;

class DateValidationActionTags extends AbstractExternalModule
{
    protected $is_survey = 0;

    protected static $Tags = array(
        '@FUTURE' => array('comparison'=>'gt', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, uses <em>today + 1</em> (or <em>now</em>) as range minimum.<br>Current date (time) is NOT within the allowed range.'),
        '@NOTPAST' => array('comparison'=>'gte', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, equivalent to using <em>today</em> (or <em>now</em>) as range minimum.'),
        '@PAST' => array('comparison'=>'lt', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, uses <em>today - 1</em> (or <em>now</em>) as range maximum.<br>Current date (time) is NOT within the allowed range.'),
        '@NOTFUTURE' => array('comparison'=>'lte', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, equivalent to using <em>today</em> (or <em>now</em>) as range maximum.')
    );

    public function redcap_every_page_before_render($project_id) {
        if (empty($project_id)) return;
        
        if (PAGE=='DataEntry/index.php' || PAGE=='surveys/index.php') {
            global $Proj;

            // $pattern /(@FUTURE|@NOTPAST|@PAST|@NOTFUTURE)\s/
            $pattern = '/('.implode('|', array_keys(static::$Tags)).')/';
            
            foreach ($Proj->metadata as $field => $attrs) {
                if (strpos($attrs['element_validation_type'], 'date')!==0) continue; // skip if not a date or datetime field
                $min = $attrs['element_validation_min'];
                $max = $attrs['element_validation_max'];
                $isDtTm = (strpos($attrs['element_validation_type'], 'time')) ? true : false;
                
                $matches = array();
                if (preg_match($pattern, $attrs['misc'], $matches)) {

                    switch ($matches[0]) {
                        case '@NOTPAST': // min today/now
                            if (empty($min)) {
                                $Proj->metadata[$field]['element_validation_min'] = ($isDtTm) ? 'now' : 'today';
                            }
                            break;
                        
                        case '@NOTFUTURE': // max is today/now
                            if (empty($max)) {
                                $Proj->metadata[$field]['element_validation_max'] = ($isDtTm) ? 'now' : 'today';
                            }
                            break;

                        case '@FUTURE': // min is tomorrow, use now if datetime
                            if (empty($min)) {
                                if ($isDtTm) {
                                    $Proj->metadata[$field]['element_validation_min'] = 'now';
                                } else {
                                    $tomorrow = new \DateTime();
                                    $tomorrow->add(new \DateInterval('P1D'));
                                    $Proj->metadata[$field]['element_validation_min'] = $tomorrow->format('Y-m-d');
                                }
                            }
                            break;
                        
                        case '@PAST': // max is yesterday, use now if datetime
                            if (empty($max)) {
                                if ($isDtTm) {
                                    $Proj->metadata[$field]['element_validation_max'] = 'now';
                                } else {
                                    $yesterday = new \DateTime();
                                    $yesterday->sub(new \DateInterval('P1D'));
                                    $Proj->metadata[$field]['element_validation_max'] = $yesterday->format('Y-m-d');
                                }
                            }
                        break;
                        
                        default:
                            break;
                    }
                }
            }

        } 
    }
}
