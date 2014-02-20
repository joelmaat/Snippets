<?php
/**
 * Project:     (D)ivision By (Z)ero Web
 * File:        Dz_Template.class.php
 *
 * Simple templating engine.
 *
 * This should technically be a good engine, because all it does is
 * the extra translation to PHP that many non programmers are unable
 * to do, thus not much parsing of the code is done. The translated
 * code is shipped of to PHP eval() for processing.
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
 * PHP version 5.3
 *
 * @package   Dz
 * @author Joel Johnson <me@joelster.com>
 * @copyright 2003 Team 229, Division By Zero
 * @license   http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @version 0.1
 */

class Dz_Template
{

    /**
     * Config variables
     */
    protected $show_error_msg = true;
    public $use_cache = true;

    /**
     * If true, then if statements will check the existence of a variable before printing it.
     * So <var name="books"> will be translated to:
     *     <?php if ($this->vars[books]) { echo $this->vars[books]; } ?>
     * rather than:
     *     <?php echo $this->vars[books]; ?>
     */
    public $safe_compiling = false;

    /**
     *  Regex "library" :)
     */
    public $find_regex = array();
    public $replace_regex = array();

    /**
     * Internal variables
     */
    protected $undefined = null;
    protected $template = null;
    protected $compiled_template = null;
    protected $evaled_template = null;
    protected $vars = array();
    protected $reset_vars = true;
    protected $db = null;
    protected $cached_templates = array();

    protected $template_sub = null;

    /**
     * Constructor for the class.. it resets the variables if you told it to.
     *
     * @param Dz_Database $database_hook Link to the active database hook.
     * @param string      $templateslist List of templates used in this execution.
     * @param boolean     $reset_vars    Whether or not to reset the internal variables.
     *
     * @return void
     */
    function Dz_Template($database_hook, $template_sub = 1, $templateslist = null, $reset_vars = TRUE)
    {
        $this->db           = $database_hook;
        $this->template_sub = intval($template_sub);
        $this->reset_vars   = $reset_vars;

        $this->find_regex    = array();
        $this->replace_regex = array();

        $this->find_regex[]    = "'<if\s*condition\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replace_regex[] = "\$this->compile_if('\\1')";

        $this->find_regex[]    = "'<else>'ie";
        $this->replace_regex[] = "\$this->compile_else()";

        $this->find_regex[]    = "'</if>'ie";
        $this->replace_regex[] = "\$this->compile_endif()";

        $this->find_regex[]    = "'<repeater\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replace_regex[] = "\$this->compile_repeater('\\1')";

        $this->find_regex[]    = "'<cycle\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*"
                                     ."values\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replace_regex[] = "\$this->compile_cycle('\\1', '\\2')";

        $this->find_regex[]    = "'{cycle\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*"
                                     ."values\s*=\s*[\"\']?(.*?)[\"\']?\s*}'ie";
        $this->replace_regex[] = "\$this->compile_cycle('\\1', '\\2')";

        $this->find_regex[]    = "'<cycler\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*"
                                     ."name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replace_regex[] = "\$this->compile_cycler('\\1', '\\2')";

        $this->find_regex[]    = "'</cycler>'ie";
        $this->replace_regex[] = "\$this->compile_endcycler()";

        $this->find_regex[]    = "'</repeater>'ie";
        $this->replace_regex[] = "\$this->compile_endrepeater()";

        $this->find_regex[]    = "'<var\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replace_regex[] = "\$this->compile_var('\\1')";

        $this->find_regex[]    = "'{var\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*}'ie";
        $this->replace_regex[] = "\$this->compile_var('\\1')";

        if ($templateslist != $this->undefined)
        {
            $this->loadTemplates($templateslist);
        }
    }

    /**
     * set the contents of the template
     *
     * @param string $template Contents of the template.
     *
     * @return void
     */
    function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * set which template sub to use
     *
     * @param int $template contents of the template
     *
     * @return void
     */
    function setTemplateSub($sub)
    {
        $this->template_sub = intval($sub);
    }

    /**
     * Loads all the templates in a comma delimited list from the
     * database to an array of cached templates.. the templates that end
     * up loaded in the array have already been compiled and only
     * need to be evaluated.
     *
     * @param string $templateslist Comma delimited list of templates used.
     *
     * @return void
     */
    function loadTemplates($templateslist)
    {
        $db = $this->db;
        global $$db;


        $templateslist = str_replace(',', "','", addslashes($templateslist));


        $temps = $$db->query("SELECT UNIX_TIMESTAMP(modifydate)-UNIX_TIMESTAMP(compiledate) "
                                 ."AS datecheck, name, raw, compiled FROM templates "
                                 ."WHERE name IN ('".$templateslist."') "
                                 ."AND subid=".$this->template_sub);


        while ($temp = $$db->fetch_array($temps))
        {
            $this->cached_templates["$temp[name]"] = $this->cache_template($temp);
        }

        unset($temp);
        $$db->free_result($temps);
    }

    /**
     * Loads one template. It checks if the template has been updated after a
     * compile and if so, it compiles the template and saves it to the database
     * otherwise, it just returns the compiled template.
     *
     * @param array|string $template Template information to cache (if array) OR name of template
     *                               to fetch and cache (if string).
     *
     * @return string Compiled template.
     */
    protected function cache_template($template)
    {
        $db = $this->db;
        global $$db;

        if (!is_array($template))
        {

            $template = $$db->query_first("SELECT UNIX_TIMESTAMP(modifydate)"
                                              ."-UNIX_TIMESTAMP(compiledate) AS datecheck, name, "
                                              ."raw, compiled FROM templates WHERE "
                                              ."name='".addslashes($template)."' AND subid="
                                              .$this->template_sub." LIMIT 1");
        }

        if ((isset($template['datecheck']) AND ($template['datecheck'] > 0))
                OR ($this->use_cache == false))
        {
            $template['compiled'] = $this->compile($template['raw']);
            $$db->query("UPDATE templates SET compiledate=NOW(), compiled='"
                            .addslashes($template['compiled'])
                            ."' WHERE name='".addslashes($template['name'])."' AND subid="
                            .$this->template_sub);
        }

        return $template['compiled'];
    }

    /**
     * Resets the internal variables.
     *
     * @return void
     */
    protected function reset_vars()
    {

        if ($this->template)
        {
            $this->template = null;
        }

        if ($this->compiled_template)
        {
            $this->compiled_template = null;
        }

        if ($this->evaled_template)
        {
            $this->evaled_template = null;
        }

        if (($this->reset_vars == true) AND ($this->vars))
        {
            unset($this->vars);
            $this->vars = array();
        }
    }

    /**
     * Assigns values to template variables.
     *
     * @param array|string $var_name The template variable name(s).
     * @param mixed        $value    The value to assign.
     *
     * @return void
     */
    function assign($var_name, $value = null)
    {
        if (is_array($var_name))
        {
            foreach ($var_name as $key => $val)
            {
                if ($key != '')
                {
                    $this->vars[$key] = $val;
                }
            }
        }
        else
        {
            if ($var_name != '')
            {
                $this->vars[$var_name] = $value;
            }
        }
    }

    /**
     * Assigns values to template variables by reference.
     *
     * @param string $var_name The template variable name(s).
     * @param mixed  $value    The value to assign.
     *
     * @return void
     */
    function assign_by_ref($var_name, &$value)
    {
        if ($var_name != '')
        {
            $this->vars[$var_name] =& $value;
        }
    }

    /**
     * clear the given assigned template variable.
     *
     * @param array|string $var_name The template variable(s) to clear.
     *
     * @return void
     */
    function clear_assign($var_name)
    {
        if (is_array($var_name))
        {
            foreach ($var_name as $curr_var)
            {
                if (isset($this->vars[$curr_var]))
                {
                    unset($this->vars[$curr_var]);
                }
            }
        }
        else
        {
            if (isset($this->vars[$var_name]))
            {
                unset($this->vars[$var_name]);
            }
        }
    }

    /**
     * Echos the evaluated template to the browser.
     *
     * @param string $template_name The name of the template to display.
     *
     * @return void
     */
    function display($template_name = null)
    {
        echo $this->get($template_name);
    }

    /**
     * Returns the evaluated template.
     *
     * @param string $template_name The name of the template to get.
     *
     * @return string Evaluated template.
     */
    function get($template_name = null)
    {
        if ($template_name == $this->undefined)
        {
            if ($this->compiled_template == $this->undefined)
            {
                $this->compiled_template = $this->compile($this->template);
            }
            
            if ($this->evaled_template == $this->undefined)
            {
                $this->evaled_template = $this->evaluate($this->compiled_template);
            }
            
            return $this->evaled_template;
        }

        if ((!isset($this->cached_templates["$template_name"])) OR ($this->cached_templates["$template_name"] == $this->undefined))
        {
            $this->cached_templates["$template_name"] = $this->cache_template($template_name);
        }

        return $this->evaluate($this->cached_templates["$template_name"]);
    }

    /**
     * Trigger template compile error.
     *
     * @param string  $error_msg  Details of error.
     * @param integer $error_type Type of error.
     *
     * @return void
     */
    function trigger_error($error_msg, $error_type = E_USER_WARNING)
    {
        if ($this->show_error_msg == true)
        {
            trigger_error("Dz_Template error: $error_msg", $error_type);
        }
    }

    /**
     * cycle through the values given.
     *
     * @param string $parent Id of the parent counter.
     * @param string $values Values to cycle though.
     *
     * @return string Next value.
     */
    protected function do_cycle($parent, $values)
    {
        static $counter;
        static $values;
        static $num_vals;

        if (!$values[$parent])
        {
            $values[$parent] = explode(',', $values);
        }

        if (!$num_vals[$parent])
        {
            $num_vals[$parent] = count($values[$parent]);
        }

        if ((!$counter[$parent]) OR ($counter[$parent] == $num_vals[$parent]))
        {
            $counter[$parent] = 0;
        }

        return $values[$parent][$counter[$parent]++];
    }

    /**
     * Compiles the template into its PHP equivalent. Best PHP compiler.. ever ;) <-- that means
     * I'm just kidding, if you didn't know.
     *
     * @param string $raw_data The actual raw template to translate to PHP.
     *
     * @return string Compile template.
     */
    protected function compile($raw_data)
    {
        if (($raw_data == "") OR ($raw_data == $this->undefined))
        {
            $this->trigger_error("template not set.");
            return "";
        }

        return preg_replace($this->find_regex, $this->replace_regex, stripslashes($raw_data));
    }

    /**
     * compiles the <if> tags of the template into its PHP equivalent
     *
     * @param string $values The contents of the condition attribute
     *
     * @return string Compiled <if> tag.
     */
    protected function compile_if($values)
    {
        $values = trim($values);
        if ((!$values) OR ($values == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values)))
        {
            $this->trigger_error("invalid if condition: '$values'.");
            return '<?php if(1==2) { ?>';
        }

        $temp = explode(".", $values);

        if ((is_array($temp)) AND (count($temp) > 1))
        {
            $thing = '$'.$temp[count($temp) - 2].'['.$temp[count($temp) - 1].']';

            $return_string = '<?php if ('.$thing.') { ?>';
        }
        else
        {
            $return_string = '<?php if ($this->vars["'.$values.'"]) { ?>';
        }

        return $return_string;
    }

    /**
     * compiles the <else> tags of the template into its PHP equivalent.
     *
     * @return string Compiled <else> tag.
     */
    protected function compile_else()
    {
        return '<?php } else { ?>';
    }

    /**
     * compiles the </if> tags of the template into its PHP equivalent.
     *
     * @return string Compiled </if> tag.
     */
    protected function compile_endif()
    {
        return '<?php } ?>';
    }

    /**
     * compiles the <repeater> tags of the template into its PHP equivalent.
     *
     * @param string $values The name of the repeater.
     *
     * @return string Compiled <repeater> tag.
     */
    protected function compile_repeater($values)
    {
        $values = trim($values);
        if ((!$values) OR ($values == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values)))
        {
            $this->trigger_error("invalid repeater name: '$values'.");
            return '<?php if(1==2) { ?>';
        }

        $temp = explode(".", $values);

        if ((is_array($temp)) AND (count($temp) > 1))
        {
            $thing = '$'.$temp[count($temp) - 2].'["'.$temp[count($temp) - 1].'"]';

            $return_string = '<?php $counter["'.$values.'"]= 1; foreach ('.$thing.' as $'
                                 .$temp[count($temp) - 1].') { $counter["'.$values
                                 .'"]= 1 - $counter["'.$values.'"]; ?>';
        }
        else
        {
            $return_string = '<?php $counter["'.$values.'"]= 1; foreach ($this->vars["'.$values
                                 .'"] as $'.$values.') { $counter["'.$values.'"]= 1 - $counter["'
                                 .$values.'"]; ?>';
        }

        return $return_string;
    }

    /**
     * compiles the </repeater> tags of the template into its PHP equivalent.
     *
     * @return string Compiled </repeater> tag.
     */
    protected function compile_endrepeater()
    {
        return '<?php } ?>';
    }

    /**
     * Compiles the <cycler> tags of the template into its PHP equivalent.
     *
     * @param string $parent The name of the cycler's parent.
     * @param string $values The name of the cycler.
     *
     * @return string Compiled <cycler> tag.
     */
    protected function compile_cycler($parent, $values)
    {
        $parent = trim($parent);
        if ((!$parent) OR ($parent == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $parent)))
        {
            $this->trigger_error("invalid cycler parent name: '$parent'.");
            return '<?php if(1==2) { ?>';
        }

        $values = trim($values);
        if ((!$values) OR ($values == "") OR (!preg_match("/default|alternate/i", $values)))
        {
            $this->trigger_error("invalid cycler name: '$values'.");
            return '<?php if(1==2) { ?>';
        }

        if ($values == "alternate")
        {
            $return_string = '<?php if($counter['.$parent.']==1) { ?>';
        }
        else
        {
            $return_string = '<?php if($counter['.$parent.']==0) { ?>';
        }

        return $return_string;
    }

    /**
     * compiles the </cycler> tags of the template into its PHP equivalent.
     *
     * @return string Compiled </cycler> tag.
     */
    protected function compile_endcycler()
    {
        return '<?php } ?>';
    }

    /**
     * Compiles the <cycle> tags of the template into its PHP equivalent.
     *
     * @param string $values the name of the cycle.
     *
     * @return string Compiled <cycle> tag.
     */
    protected function compile_cycle($parent, $values)
    {
        $parent = trim($parent);
        if ((!$parent) OR ($parent == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $parent)))
        {
            $this->trigger_error("invalid cycle parent name: '$parent'.");
        }

        $values = trim($values);
        if ((!$values) OR ($values == ""))
        {
            $this->trigger_error("invalid cycle values: '$values'.");
        }

        $return_string = '<?php echo $this->do_cycle("'.$parent.'","'.$values.'"); ?>';

        return $return_string;
    }


    /**
     * compiles the <var> tags of the template into its PHP equivalent.
     *
     * @param string $values The name of the variable.
     *
     * @return string Compiled <var> tag.
     */
    protected function compile_var($values)
    {
        $values = trim($values);
        if ((!$values) OR ($values == "") OR (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values)))
            {
            $this->trigger_error("invalid var name: '$values'.");
            return '';
        }

        $temp = explode(".", $values);
        if ((is_array($temp)) AND (count($temp) > 1))
        {
            $thing = '$'.$temp[count($temp) - 2].'["'.$temp[count($temp) - 1].'"]';
            if ($this->safe_compiling == true)
            {
                $return_string = '<?php if (isset('.$thing.')) { echo '.$thing.'; } ?>';
            }
            else
            {
                $return_string = '<?php echo '.$thing.'; ?>';
            }
        }
        else
        {
            if ($this->safe_compiling == true)
            {
                $return_string = '<?php if (isset($this->vars["'.$values
                                     .'"])) { echo $this->vars["'.$values.'"]; } ?>';
            }
            else
            {
                $return_string = '<?php echo $this->vars["'.$values.'"]; ?>';
            }
        }

        return $return_string;

    }

    /**
     * Evaluates the compiled template to raw HTML.
     *
     * @param string $compiled_template The actual PHP version of the template that is to be
     *                                  evaluated.
     *
     * @return string Evaluated template.
     */
    protected function evaluate($compiled_template)
    {
        ob_start();
        eval("?".chr(62).$compiled_template.chr(60)."?php ");
        $evaled = ob_get_contents();
        ob_end_clean();

        $this->reset_vars();

        return $evaled;
    }
}
