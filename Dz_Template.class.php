<?php
/**
 * Project:     (D)ivision By (Z)ero Web
 * File:        Dz_Template.class.php
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 *
 * @author Joel Johnson <me@joelster.com>
 * @version 0.1
 * @copyright 2003 Team 229, Division By Zero
 *
 * This should technically be a good engine, because all it does is
 * the extra translation to PHP that many non programmers are unable
 * to do, thus not much parsing of the code is done. The translated 
 * code is shipped of to PHP eval() for processing.
 */

class Dz_Template
{
    
    /**
     * Config variables
     */
    var $_show_error_msg = true; //whether or not to echo the error messages the class may encounter
    var $use_cache = true;
    
    /**
     * if true, then if statements will check the existence of a 
     * variable before printing it.
     * so <var name="books"> will be translated to:
     * <?php if ($this->_vars[books]) { echo $this->_vars[books]; } ?> 
     * rather than:
     * <?php echo $this->_vars[books]; ?>
     */
    var $safe_compiling = false;
    
    /**
     *  Regex "library" :)
     */
    var $find_regex = array();
    var $replace_regex = array();
    
    /**
     * Internal variables
     */
    var $_undefined = null; //mapped version of null ;)
    var $_template = null; //the actual template contents
    var $_compiled_template = null; //the compiled template
    var $_evaled_template = null; //the evaluated tempate file (went through the eval() function)
    var $_vars = array(); //array of variables set by the user
    var $_reset_vars = true; //reset vars on new template?
    var $_db = null; //pointer to the database containing the table 'templates' 
    var $_cached_templates = array(); //array of templates used in this execution.. the database thanks us
    
    var $_template_sub = null; //template set from which to grab templates
    
    /**
     * constructor for the class.. it resets the variables
     * if you told it to.
     * @param Dz_Database $database_hook link to the active database hook
     * @param string $templateslist list of templates used in this execution
     * @param boolean $reset_vars whether or not to reset the internal variables
     */
    function Dz_Template($database_hook, $template_sub = 1, $templateslist = null, $reset_vars = TRUE)
    {
        $this->_db           = $database_hook;
        $this->_template_sub = intval($template_sub);
        $this->_reset_vars   = $reset_vars;
        
        $this->find_regex    = array();
        $this->replace_regex = array();
        
        $this->find_regex[]    = "'<if\s*condition\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie"; //replace <if> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_if('\\1')";
        
        $this->find_regex[]    = "'<else>'ie"; //replace <else> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_else()";
        
        $this->find_regex[]    = "'</if>'ie"; //replace </if> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_endif()";
        
        $this->find_regex[]    = "'<repeater\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie"; //replace <repeater> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_repeater('\\1')";
        
        $this->find_regex[]    = "'<cycle\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*values\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie"; //replace <cycle> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_cycle('\\1', '\\2')";
        
        $this->find_regex[]    = "'{cycle\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*values\s*=\s*[\"\']?(.*?)[\"\']?\s*}'ie"; //replace <cycle> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_cycle('\\1', '\\2')";
        
        $this->find_regex[]    = "'<cycler\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie"; //replace <cycler> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_cycler('\\1', '\\2')";
        
        $this->find_regex[]    = "'</cycler>'ie"; //replace </cycler> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_endcycler()";
        
        $this->find_regex[]    = "'</repeater>'ie"; //replace </repeater> with PHP equivalent
        $this->replace_regex[] = "\$this->_compile_endrepeater()";
        
        $this->find_regex[]    = "'<var\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie"; //replace <var> with PHP variable
        $this->replace_regex[] = "\$this->_compile_var('\\1')";
        
        $this->find_regex[]    = "'{var\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*}'ie"; //replace <var> with PHP variable
        $this->replace_regex[] = "\$this->_compile_var('\\1')";
        
        if ($templateslist != $this->_undefined) {
            $this->loadTemplates($templateslist);
        }
    }
    
    /**
     * set the contents of the template
     * 
     * @param string $template contents of the template
     */
    function setTemplate($template)
    {
        $this->_template = $template;
    }
    
    /**
     * set which template sub to use
     * 
     * @param int $template contents of the template
     */
    function setTemplateSub($sub)
    {
        $this->_template_sub = intval($sub);
    }
    
    /**
     * loads all the templates in a comma delimited list from the 
     * database to an array of cached templates.. the templates that end
     * up loaded in the array have already been compiled and only
     * need to be evaluated.
     * 
     * @param string $templateslist comma delimited list of templates used
     */
    function loadTemplates($templateslist)
    {
        $db = $this->_db;
        global $$db;
        
        // add in sql info
        $templateslist = str_replace(',', "','", addslashes($templateslist));
        
        // run query
        $temps = $$db->query("SELECT UNIX_TIMESTAMP(modifydate)-UNIX_TIMESTAMP(compiledate) AS datecheck, name, raw, compiled FROM templates WHERE name IN ('" . $templateslist . "') AND subid=" . $this->_template_sub . "");
        
        // cache templates
        while ($temp = $$db->fetch_array($temps)) {
            $this->_cached_templates["$temp[name]"] = $this->_cache_template($temp);
        }
        
        unset($temp);
        $$db->free_result($temps);
    }
    
    /**
     * Loads one template. It checks if the template has been updated after a
     * compile and if so, it compiles the template and saves it to the database
     * otherwise, it just returns the compiled template.
     * 
     * @param array|string $template template information to cache (if array) OR name of template to fetch and cache (if string)
     */
    function _cache_template($template)
    {
        $db = $this->_db;
        global $$db;
        
        if (!is_array($template)) //if we weren't given the template info
            {
            // run query
            $template = $$db->query_first("SELECT UNIX_TIMESTAMP(modifydate)-UNIX_TIMESTAMP(compiledate) AS datecheck, name, raw, compiled FROM templates WHERE name='" . addslashes($template) . "' AND subid=" . $this->_template_sub . " LIMIT 1");
        }
        if (((isset($template['datecheck'])) AND ($template['datecheck'] > 0)) OR ($this->use_cache == false)) //if modifydate larger, compile the raw template and save it back to database
            {
            $template['compiled'] = $this->_compile($template['raw']); //compile the raw template
            $$db->query("UPDATE templates SET compiledate=NOW(), compiled='" . addslashes($template['compiled']) . "' WHERE name='" . addslashes($template['name']) . "' AND subid=" . $this->_template_sub . ""); //update the compiled version in the database
        }
        
        return $template['compiled'];
    }
    
    /**
     * resets the internal variables.
     */
    function _reset_vars()
    {
        //clear any previous values that may be here.. if we are supposed to
        if ($this->_template) {
            $this->_template = null;
        }
        if ($this->_compiled_template) {
            $this->_compiled_template = null;
        }
        if ($this->_evaled_template) {
            $this->_evaled_template = null;
        }
        if (($this->_reset_vars == true) AND ($this->_vars)) {
            unset($this->_vars);
            $this->_vars = array();
        }
    }
    
    /**
     * assigns values to template variables
     *
     * @param array|string $var_name the template variable name(s)
     * @param mixed $value the value to assign
     */
    function assign($var_name, $value = null)
    {
        if (is_array($var_name)) {
            foreach ($var_name as $key => $val) {
                if ($key != '') {
                    $this->_vars[$key] = $val;
                }
            }
        } else {
            if ($var_name != '') {
                $this->_vars[$var_name] = $value;
            }
        }
    }
    
    /**
     * assigns values to template variables by reference
     *
     * @param string $var_name the template variable name(s)
     * @param mixed $value the value to assign
     */
    function assign_by_ref($var_name, &$value)
    {
        if ($var_name != '') {
            $this->_vars[$var_name] =& $value;
        }
    }
    
    /**
     * clear the given assigned template variable.
     *
     * @param array|string $var_name the template variable(s) to clear
     */
    function clear_assign($var_name)
    {
        if (is_array($var_name)) {
            foreach ($var_name as $curr_var) {
                if (isset($this->_vars[$curr_var])) {
                    unset($this->_vars[$curr_var]);
                }
            }
        } else {
            if (isset($this->_vars[$var_name])) {
                unset($this->_vars[$var_name]);
            }
        }
    }
    
    /**
     * echos the evaluated template to the browser
     * 
     * @param string $template_name the name of the template to display
     */
    function display($template_name = null)
    {
        echo $this->get($template_name);
    }
    
    /**
     * returns the evaluated template
     * 
     * @param string $template_name the name of the template to get
     */
    function get($template_name = null)
    {
        if ($template_name == $this->_undefined) {
            if ($this->_compiled_template == $this->_undefined) {
                $this->_compiled_template = $this->_compile($this->_template);
            }
            if ($this->_evaled_template == $this->_undefined) {
                $this->_evaled_template = $this->_evaluate($this->_compiled_template);
            }
            return $this->_evaled_template;
        }
        if ((!isset($this->_cached_templates["$template_name"])) OR ($this->_cached_templates["$template_name"] == $this->_undefined)) {
            $this->_cached_templates["$template_name"] = $this->_cache_template($template_name);
        }
        return $this->_evaluate($this->_cached_templates["$template_name"]);
    }
    
    /**
     * trigger template compile error
     *
     * @param string $error_msg
     * @param integer $error_type
     */
    function trigger_error($error_msg, $error_type = E_USER_WARNING)
    {
        if ($_show_error_msg == true) {
            trigger_error("Dz_Template error: $error_msg", $error_type);
        }
    }
    
    /**
     * cycle through the values given
     *
     * @param string $parent
     * @param string $values
     */
    function _do_cycle($parent, $values)
    {
        static $counter;
        static $values;
        static $num_vals;
        
        if (!$values[$parent]) {
            $values[$parent] = explode(',', $values);
        }
        
        if (!$num_vals[$parent]) {
            $num_vals[$parent] = count($values[$parent]);
        }
        
        if ((!$counter[$parent]) OR ($counter[$parent] == $num_vals[$parent])) {
            $counter[$parent] = 0;
        }
        
        return $values[$parent][$counter[$parent]++];
    }
    
    /**
     * compiles the template into its PHP equivalent.
     * best PHP compiler.. ever ;) <-- that means I'm just kidding, if
     * you didn't know.
     * 
     * @param string $raw_data the actual raw template to translate to PHP
     */
    function _compile($raw_data)
    {
        if (($raw_data == "") OR ($raw_data == $this->_undefined)) {
            $this->trigger_error("template not set.");
            return "";
        }
        
        return preg_replace($this->find_regex, $this->replace_regex, stripslashes($raw_data)); //translate to PHP
    }
    
    /**
     * compiles the <if> tags of the template into its PHP equivalent
     * 
     * @param string $values the contents of the condition attribute
     */
    function _compile_if($values)
    {
        $values = trim($values);
        if ((!$values) OR ($values == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values))) //no blanks, or non alphanumeric_. allowed
            {
            $this->trigger_error("invalid if condition: '$values'.");
            return '<?php if(1==2) { ?>'; //Just so the error doesn't completely break the code.
        }
        
        $temp = explode(".", $values);
        
        if ((is_array($temp)) AND (count($temp) > 1)) { //books][temp
            $thing = '$' . $temp[count($temp) - 2] . '[' . $temp[count($temp) - 1] . ']';
            
            $return_string = '<?php if (' . $thing . ') { ?>';
        } else {
            $return_string = '<?php if ($this->_vars["' . $values . '"]) { ?>';
        }
        
        return $return_string;
    }
    
    /**
     * compiles the <else> tags of the template into its PHP equivalent
     */
    function _compile_else()
    {
        return '<?php } else { ?>';
    }
    
    /**
     * compiles the </if> tags of the template into its PHP equivalent
     */
    function _compile_endif()
    {
        return '<?php } ?>';
    }
    
    /**
     * compiles the <repeater> tags of the template into its PHP equivalent
     * @param string $values the name of the repeater
     */
    function _compile_repeater($values) //The problem child!!!
    {
        $values = trim($values);
        if ((!$values) OR ($values == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values))) {
            $this->trigger_error("invalid repeater name: '$values'.");
            return '<?php if(1==2) { ?>'; //Just so the error doesn't completely break the code.
        }
        
        $temp = explode(".", $values);
        
        if ((is_array($temp)) AND (count($temp) > 1)) { //books][temp
            $thing = '$' . $temp[count($temp) - 2] . '["' . $temp[count($temp) - 1] . '"]';
            
            $return_string = '<?php $counter["' . $values . '"]= 1; foreach (' . $thing . ' as $' . $temp[count($temp) - 1] . ') { $counter["' . $values . '"]= 1 - $counter["' . $values . '"]; ?>';
        } else {
            $return_string = '<?php $counter["' . $values . '"]= 1; foreach ($this->_vars["' . $values . '"] as $' . $values . ') { $counter["' . $values . '"]= 1 - $counter["' . $values . '"]; ?>';
        }
        
        return $return_string;
    }
    
    /**
     * compiles the </repeater> tags of the template into its PHP equivalent
     */
    function _compile_endrepeater()
    {
        return '<?php } ?>';
    }
    
    /**
     * compiles the <cycler> tags of the template into its PHP equivalent
     * @param string $values the name of the cycler
     */
    function _compile_cycler($parent, $values)
    {
        $parent = trim($parent);
        if ((!$parent) OR ($parent == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $parent))) {
            $this->trigger_error("invalid cycler parent name: '$parent'.");
            return '<?php if(1==2) { ?>'; //Just so the error doesn't completely break the code.
        }
        
        $values = trim($values);
        if ((!$values) OR ($values == "") OR (!preg_match("/default|alternate/i", $values))) {
            $this->trigger_error("invalid cycler name: '$values'.");
            return '<?php if(1==2) { ?>'; //Just so the error doesn't completely break the code.
        }
        
        if ($values == "alternate") {
            $return_string = '<?php if($counter[' . $parent . ']==1) { ?>';
        } else {
            $return_string = '<?php if($counter[' . $parent . ']==0) { ?>';
        }
        
        return $return_string;
    }
    
    /**
     * compiles the </cycler> tags of the template into its PHP equivalent
     */
    function _compile_endcycler()
    {
        return '<?php } ?>';
    }
    
    /**
     * compiles the <cycle> tags of the template into its PHP equivalent
     * @param string $values the name of the cycle
     */
    function _compile_cycle($parent, $values)
    {
        $parent = trim($parent);
        if ((!$parent) OR ($parent == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $parent))) {
            $this->trigger_error("invalid cycle parent name: '$parent'.");
        }
        
        $values = trim($values);
        if ((!$values) OR ($values == "")) {
            $this->trigger_error("invalid cycle values: '$values'.");
        }
        
        $return_string = '<?php echo $this->_do_cycle("' . $parent . '","' . $values . '"); ?>';
        
        return $return_string;
    }
    
    
    /**
     * compiles the <var> tags of the template into its PHP equivalent
     * @param string $values the name of the variable
     */
    function _compile_var($values)
    {
        $values = trim($values);
        if ((!$values) OR ($values == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values))) //no blanks, or non alphanumeric_. allowed
            {
            $this->trigger_error("invalid var name: '$values'.");
            return ''; //Just so the error doesn't completely break the code.
        }
        
        $temp = explode(".", $values);
        
        if ((is_array($temp)) AND (count($temp) > 1)) { //books][temp
            $thing = '$' . $temp[count($temp) - 2] . '["' . $temp[count($temp) - 1] . '"]';
            
            if ($this->safe_compiling == true) {
                $return_string = '<?php if (isset(' . $thing . ')) { echo ' . $thing . '; } ?>';
            } else {
                $return_string = '<?php echo ' . $thing . '; ?>';
            }
        } else {
            if ($this->safe_compiling == true) {
                $return_string = '<?php if (isset($this->_vars["' . $values . '"])) { echo $this->_vars["' . $values . '"]; } ?>';
            } else {
                $return_string = '<?php echo $this->_vars["' . $values . '"]; ?>';
            }
        }
        
        return $return_string;
        
    }
    
    /**
     * Evaluates the compiled template to raw HTML
     * 
     * @param string $compiled_template the actual PHP version of the template that is to be evaluated
     */
    function _evaluate($compiled_template)
    {
        //eval the compiled template and buffer the output, then assign it to $evaled
        ob_start();
        eval("?" . chr(62) . $compiled_template . chr(60) . "?php ");
        $evaled = ob_get_contents();
        ob_end_clean();
        
        $this->_reset_vars();
        
        return $evaled; //return evaled (HTML)
    }
}
