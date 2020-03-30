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
        protected static $Tags = array(
            '@FUTURE' => array('comparison'=>'gt', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, validate that the value entered is <u>after today (or now)</u>.<br>Current date (time) is NOT within the allowed range.'),
            '@NOTPAST' => array('comparison'=>'gte', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, validate that the value entered is <u>today (or now) or after</u>.<br>Current date (time) IS within the allowed range.'),
            '@PAST' => array('comparison'=>'lt', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, validate that the value entered is <u>before today (or now)</u>.<br>Current date (time) is NOT within the allowed range.'),
            '@NOTFUTURE' => array('comparison'=>'lte', 'description'=>'Date Validation Action Tags<br>For a date (or datetime) field, validate that the value entered is <u>today (or now) or before</u>.<br>Current date (time) IS within the allowed range.'),
        );
        
        public function redcap_data_entry_form_top($project_id, $record=null, $instrument, $event_id, $group_id=null, $repeat_instance=1) {
                $this->include($instrument);
        }

        public function redcap_survey_page_top($project_id, $record=null, $instrument, $event_id, $group_id=null, $survey_hash=null, $response_id=null, $repeat_instance=1) {
                $this->include($instrument);
        }
        
        /**
         * Augment the action_tag_explain content on project Design pages by 
         * adding some additional tr following the last built-in action tag.
         * @param type $project_id
         */
        public function redcap_every_page_before_render($project_id) {
                if (PAGE==='Design/action_tag_explain.php') {
                        global $lang;
                        $lastActionTagDesc = end(\Form::getActionTags());

                        // which $lang element is this?
                        $langElement = array_search($lastActionTagDesc, $lang);
                        
                        foreach (static::$Tags as $tag => $tagAttr) {
                                $lastActionTagDesc .= "</td></tr>";
                                $lastActionTagDesc .= $this->makeTagTR($tag, $tagAttr['description']);
                        }                        
                        $lang[$langElement] = rtrim(rtrim(rtrim(trim($lastActionTagDesc), '</tr>')),'</td>');
                }
        }
        
        protected function include($instrument) {
                $taggedFields = array();
                $tags = static::$Tags;
                $instrumentFields = \REDCap::getDataDictionary('array', false, true, $instrument);
                
                // $pattern /(@FUTURE|@NOTPAST|@PAST|@NOTFUTURE)\s/
                $pattern = '/('.implode('|', array_keys($tags)).')/';
                foreach ($instrumentFields as $fieldName => $fieldDetails) {
                        $matches = array();
                        if (preg_match($pattern, $fieldDetails['field_annotation'], $matches)) {
                                $comparison = $tags[$matches[0]]['comparison'];
                                $taggedFields[$fieldName] = $comparison;
                        }
                }
                if (count($taggedFields)>0) {
                        $this->includeJS($taggedFields);
                }
        }
        
        protected function includeJS($taggedFields) {
                ?>
<script type="text/javascript">
    $(document).ready(function(){

        var em_blur = function (ob, comparison, format) {
            const now = new Date();
            const tzOffsetMs = now.getTimezoneOffset() * 60 * 1000;
            if (format.includes("seconds")) {
                var dtstrLen = 19;
                switch(comparison) {
                  case 'gt' : var tagOffsetMs = 1*1000; break;
                  case 'lt' : var tagOffsetMs = -1*1000; break;
                  default: var tagOffsetMs = 0;
                }
            } else if (format.includes("time")) {
                var dtstrLen = 16;
                switch(comparison) {
                  case 'gt' : var tagOffsetMs = 60*1000; break;
                  case 'lt' : var tagOffsetMs = -60*1000; break;
                  default: var tagOffsetMs = 0;
                }
            } else {
                var dtstrLen = 10;
                switch(comparison) {
                  case 'gt' : var tagOffsetMs = 24*60*60*1000; break;
                  case 'lt' : var tagOffsetMs = -24*60*60*1000; break;
                  default: var tagOffsetMs = 0;
                }
            }
            const dateLocal = new Date(now.getTime() - tzOffsetMs + tagOffsetMs);
            const dtstr = dateLocal.toISOString().slice(0, dtstrLen).replace("T", " ");
            
            switch(comparison) {
              case 'gt' : var min = dtstr, max = ''; break;
              case 'gte': var min = dtstr, max = ''; break;
              case 'lt' : var min = '', max = dtstr; break;
              case 'lte': var min = '', max = dtstr; break;
              default: var min = '', max = '';
            }
            //console.log('min='+min+' max='+max);
            if (dataEntryFormValuesChanged) {
                redcap_validate(ob, min, max, 'soft_typed', format, 1);
            }
        };
        
        var taggedFields = JSON.parse('<?php echo json_encode($taggedFields); ?>');
        //console.log(taggedFields);
        Object.keys(taggedFields).forEach(function(fld) {
            var input = $('input[name='+fld+']');
            if (input.length) { // instrument field may not be on current page of survey
                //console.log(fld+': '+taggedFields[fld]);
                $(input).on('blur', function() {
                    em_blur(this,taggedFields[fld],input.attr('fv'));
                });
            }
        });
    });
</script>
                <?php
        }
        
        /**
         * Make a table row for an action tag copied from 
         * v8.5.0/Design/action_tag_explain.php
         * @global type $isAjax
         * @param type $tag
         * @param type $description
         * @return type
         */
        protected function makeTagTR($tag, $description) {
                global $isAjax, $lang;
                return \RCView::tr(array(),
			\RCView::td(array('class'=>'nowrap', 'style'=>'text-align:center;background-color:#f5f5f5;color:#912B2B;padding:7px 15px 7px 12px;font-weight:bold;border:1px solid #ccc;border-bottom:0;border-right:0;'),
				((!$isAjax || (isset($_POST['hideBtns']) && $_POST['hideBtns'] == '1')) ? '' :
					\RCView::button(array('class'=>'btn btn-xs btn-rcred', 'style'=>'', 'onclick'=>"$('#field_annotation').val(trim('".js_escape($tag)." '+$('#field_annotation').val())); highlightTableRowOb($(this).parentsUntil('tr').parent(),2500);"), $lang['design_171'])
				)
			) .
			\RCView::td(array('class'=>'nowrap', 'style'=>'background-color:#f5f5f5;color:#912B2B;padding:7px;font-weight:bold;border:1px solid #ccc;border-bottom:0;border-left:0;border-right:0;'),
				$tag
			) .
			\RCView::td(array('style'=>'font-size:12px;background-color:#f5f5f5;padding:7px;border:1px solid #ccc;border-bottom:0;border-left:0;'),
				'<i class="fas fa-cube mr-1"></i>'.$description
			)
		);

        }
}
