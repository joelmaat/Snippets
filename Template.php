<?php
/**
 * Project:     (D)ivision By (Z)ero Web
 * File:        Template.php
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
 * @category  Dz
 * @package   Dz
 * @author    Joel Johnson <me@joelster.com>
 * @copyright 2003 Team 229, Division By Zero
 * @license   http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @version   0.1
 */

namespace Dz;

/**
 * Simple templating engine.
 *
 * @category  Dz
 * @package   Dz
 * @author    Joel Johnson <me@joelster.com>
 * @copyright 2003 Team 229, Division By Zero
 * @license   http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @version   0.1
 */
class Template
{

    /**
     * Should a message be printed to the browser in the even of an error.
     *
     * @var boolean
     */
    protected $showErrorMsg = true;

    /**
     * Should compiled templates be cached.
     *
     * @var boolean
     */
    public $useCache = true;

    /**
     * If true, then if statements will check the existence of a variable before printing it.
     *
     * So <var name="books"> will be translated to:
     *     <?php if ($this->vars[books]) { echo $this->vars[books]; } ?>
     * rather than:
     *     <?php echo $this->vars[books]; ?>
     *
     * @var boolean
     */
    public $safeCompiling = false;

    /**
     * List of match patterns for placeholders (if, repeater, etc) in template.
     *
     * @var string[]
     */
    public $findRegex = array();

    /**
     * List of replacements for match patterns for placeholders in template.
     *
     * @var string[]
     */
    public $replaceRegex = array();

    /**
     * Alias for null.
     *
     * @var null
     */
    protected $undefined = null;

    /**
     * Current template(s).
     *
     * @var string|string[]
     */
    protected $template = null;

    /**
     * Compiled (custom syntax replaced with PHP code) template(s).
     *
     * @var string|string[]
     */
    protected $compiledTemplate = null;

    /**
     * Evaluated (placeholders replaced with actual values) template(s).
     *
     * @var string|string[]
     */
    protected $evaledTemplate = null;

    /**
     * Placeholder:replacement mapping.
     *
     * @var string[]
     */
    protected $vars = array();

    /**
     * Should the list of vars be emptied on a call to _resetVars (if false just clears template
     * data).
     *
     * @var boolean
     */
    protected $resetVars = true;

    /**
     * Name of variable to which database instance was assigned.
     *
     * @var string
     */
    protected $database = null;

    /**
     * Cached templates (to reduce database calls).
     *
     * @var string[]
     */
    protected $cachedTemplates = array();

    /**
     * Subset (or group) from which to pull requested template. This allows changing of styles
     * while referencing the same template names.
     *
     * @var integer
     */
    protected $templateSub = null;

    /**
     * Constructor for the class.. it resets the variables if you told it to.
     *
     * @param string      $databaseHook  Name of variable to which database instance was assigned.
     * @param integer     $templateSub   Group to which templates belong.
     * @param string|null $templatesList Comma seperated list of templates used in this execution.
     * @param boolean     $resetVars     Whether or not to reset the internal variables.
     */
    public function __construct($databaseHook,
                                $templateSub = 1,
                                $templatesList = null,
                                $resetVars = true)
    {
        $this->database     = $databaseHook;
        $this->templateSub  = intval($templateSub);
        $this->resetVars    = $resetVars;

        $this->findRegex    = array();
        $this->replaceRegex = array();

        $this->findRegex[]    = "'<if\s*condition\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replaceRegex[] = "\$this->compileIf('\\1')";

        $this->findRegex[]    = "'<else>'ie";
        $this->replaceRegex[] = "\$this->compileElse()";

        $this->findRegex[]    = "'</if>'ie";
        $this->replaceRegex[] = "\$this->compileEndif()";

        $this->findRegex[]    = "'<repeater\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replaceRegex[] = "\$this->compileRepeater('\\1')";

        $this->findRegex[]    = "'<cycle\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*"
                                     ."values\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replaceRegex[] = "\$this->compileCycle('\\1', '\\2')";

        $this->findRegex[]    = "'{cycle\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*"
                                     ."values\s*=\s*[\"\']?(.*?)[\"\']?\s*}'ie";
        $this->replaceRegex[] = "\$this->compileCycle('\\1', '\\2')";

        $this->findRegex[]    = "'<cycler\s*parent\s*=\s*[\"\']?(.*?)[\"\']?\s*"
                                     ."name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replaceRegex[] = "\$this->compileCycler('\\1', '\\2')";

        $this->findRegex[]    = "'</cycler>'ie";
        $this->replaceRegex[] = "\$this->compileEndcycler()";

        $this->findRegex[]    = "'</repeater>'ie";
        $this->replaceRegex[] = "\$this->compileEndrepeater()";

        $this->findRegex[]    = "'<var\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*>'ie";
        $this->replaceRegex[] = "\$this->compileVar('\\1')";

        $this->findRegex[]    = "'{var\s*name\s*=\s*[\"\']?(.*?)[\"\']?\s*}'ie";
        $this->replaceRegex[] = "\$this->compileVar('\\1')";

        if ($templatesList != $this->undefined)
        {
            $this->loadTemplates($templatesList);
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
     * @param integer $sub Subset (or group) for templates.
     *
     * @return void
     */
    function setTemplateSub($sub)
    {
        $this->templateSub = intval($sub);
    }

    /**
     * Loads all the templates in a comma delimited list from the
     * database to an array of cached templates.. the templates that end
     * up loaded in the array have already been compiled and only
     * need to be evaluated.
     *
     * @param string $templatesList Comma delimited list of templates used.
     *
     * @return void
     */
    function loadTemplates($templatesList)
    {
        $database = $this->database;
        global $$database;


        $templatesList = str_replace(',', "','", addslashes($templatesList));


        $temps = $$database->query("SELECT UNIX_TIMESTAMP(modifydate)-UNIX_TIMESTAMP(compiledate) "
                                       ."AS datecheck, name, raw, compiled FROM templates "
                                       ."WHERE name IN ('".$templatesList."') "
                                       ."AND subid=".$this->templateSub);


        while ($temp = $$database->fetchArray($temps))
        {
            $this->cachedTemplates["$temp[name]"] = $this->cacheTemplate($temp);
        }

        unset($temp);
        $$database->freeResult($temps);
    }

    /**
     * Loads one template. It checks if the template has been updated after a
     * compile and if so, it compiles the template and saves it to the database
     * otherwise, it just returns the compiled template.
     *
     * @param string[]|string $template Template information to cache (if array) OR name of
     *                                  template to fetch and cache (if string).
     *
     * @return string Compiled template.
     */
    protected function cacheTemplate($template)
    {
        $database = $this->database;
        global $$database;

        if (!is_array($template))
        {

            $template = $$database->queryFirst("SELECT UNIX_TIMESTAMP(modifydate)-"
                                                   ."UNIX_TIMESTAMP(compiledate) AS datecheck, "
                                                   ."name, raw, compiled FROM templates WHERE "
                                                   ."name='".addslashes($template)."' AND subid="
                                                   .$this->templateSub." LIMIT 1");
        }

        if ((isset($template['datecheck']) && ($template['datecheck'] > 0))
                || ($this->useCache == false))
        {
            $template['compiled'] = $this->compile($template['raw']);
            $$database->query("UPDATE templates SET compiledate=NOW(), compiled='"
                                  .addslashes($template['compiled'])
                                  ."' WHERE name='".addslashes($template['name'])."' AND subid="
                                  .$this->templateSub);
        }

        return $template['compiled'];
    }

    /**
     * Resets the internal variables.
     *
     * @return void
     */
    protected function _resetVars()
    {

        if ($this->template)
        {
            $this->template = null;
        }

        if ($this->compiledTemplate)
        {
            $this->compiledTemplate = null;
        }

        if ($this->evaledTemplate)
        {
            $this->evaledTemplate = null;
        }

        if (($this->resetVars == true) && ($this->vars))
        {
            unset($this->vars);
            $this->vars = array();
        }
    }

    /**
     * Assigns values to template variables.
     *
     * @param mixed[]|string $varName The template variable name(s).
     * @param mixed          $value   The value to assign.
     *
     * @return void
     */
    function assign($varName, $value = null)
    {
        if (is_array($varName))
        {
            foreach ($varName as $key => $val)
            {
                if ($key != '')
                {
                    $this->vars[$key] = $val;
                }
            }
        }
        else
        {
            if ($varName != '')
            {
                $this->vars[$varName] = $value;
            }
        }
    }

    /**
     * Assigns values to template variables by reference.
     *
     * @param string $varName The template variable name(s).
     * @param mixed  &$value  The value to assign.
     *
     * @return void
     */
    function assignByRef($varName, &$value)
    {
        if ($varName != '')
        {
            $this->vars[$varName] = &$value;
        }
    }

    /**
     * clear the given assigned template variable.
     *
     * @param string[]|string $varName The template variable(s) to clear.
     *
     * @return void
     */
    function clearAssign($varName)
    {
        if (is_array($varName))
        {
            foreach ($varName as $currVar)
            {
                if (isset($this->vars[$currVar]))
                {
                    unset($this->vars[$currVar]);
                }
            }
        }
        else
        {
            if (isset($this->vars[$varName]))
            {
                unset($this->vars[$varName]);
            }
        }
    }

    /**
     * Echos the evaluated template to the browser.
     *
     * @param string|null $templateName The name of the template to display.
     *
     * @return void
     */
    function display($templateName = null)
    {
        echo $this->get($templateName);
    }

    /**
     * Returns the evaluated template.
     *
     * @param string $templateName The name of the template to get.
     *
     * @return string Evaluated template.
     */
    function get($templateName = null)
    {
        if ($templateName == $this->undefined)
        {
            if ($this->compiledTemplate == $this->undefined)
            {
                $this->compiledTemplate = $this->compile($this->template);
            }

            if ($this->evaledTemplate == $this->undefined)
            {
                $this->evaledTemplate = $this->evaluate($this->compiledTemplate);
            }

            return $this->evaledTemplate;
        }

        if (!isset($this->cachedTemplates["$templateName"])
                || ($this->cachedTemplates["$templateName"] == $this->undefined))
        {
            $this->cachedTemplates["$templateName"] = $this->cacheTemplate($templateName);
        }

        return $this->evaluate($this->cachedTemplates["$templateName"]);
    }

    /**
     * Trigger template compile error.
     *
     * @param Exception  $exception Exception to be wrapped and thrown if
     *                              $showErrorMsg is enabled.
     * @param integer    $errorType Type of error.
     *
     * @return void
     *
     * @throws Exception If _showErrorMsg is enabled the exception passed in will be
     *                   wrapped and thrown.
     */
    function triggerError(Exception $exception, $errorType = E_USER_WARNING)
    {
        if ($this->showErrorMsg == true)
        {
            throw new Exception("Dz_Template error", $errorType, $exception);
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
    protected function doCycle($parent, $values)
    {
        static $counter;
        static $values;
        static $numVals;

        if (!$values[$parent])
        {
            $values[$parent] = explode(',', $values);
        }

        if (!$numVals[$parent])
        {
            $numVals[$parent] = count($values[$parent]);
        }

        if ((!$counter[$parent]) || ($counter[$parent] == $numVals[$parent]))
        {
            $counter[$parent] = 0;
        }

        return $values[$parent][$counter[$parent]++];
    }

    /**
     * Compiles the template into its PHP equivalent. Best PHP compiler.. ever ;) <-- that means
     * I'm just kidding, if you didn't know.
     *
     * @param string $rawData The actual raw template to translate to PHP.
     *
     * @return string Compile template.
     */
    protected function compile($rawData)
    {
        if (($rawData == "") || ($rawData == $this->undefined))
        {
            $this->triggerError(new InvalidArgumentException("template not set."));
            return "";
        }

        return preg_replace($this->findRegex, $this->replaceRegex, stripslashes($rawData));
    }

    /**
     * compiles the <if> tags of the template into its PHP equivalent
     *
     * @param string $values The contents of the condition attribute.
     *
     * @return string Compiled <if> tag.
     */
    protected function compileIf($values)
    {
        $values = trim($values);
        if ((!$values) || ($values == "") || (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values)))
        {
            $this->triggerError(new InvalidArgumentException("invalid if condition: '$values'."));
            return '<?php if(1==2) { ?>';
        }

        $temp = explode(".", $values);

        if ((is_array($temp)) && (count($temp) > 1))
        {
            $thing = '$'.$temp[count($temp) - 2].'['.$temp[count($temp) - 1].']';

            $returnString = '<?php if ('.$thing.') { ?>';
        }
        else
        {
            $returnString = '<?php if ($this->vars["'.$values.'"]) { ?>';
        }

        return $returnString;
    }

    /**
     * compiles the <else> tags of the template into its PHP equivalent.
     *
     * @return string Compiled <else> tag.
     */
    protected function compileElse()
    {
        return '<?php } else { ?>';
    }

    /**
     * compiles the </if> tags of the template into its PHP equivalent.
     *
     * @return string Compiled </if> tag.
     */
    protected function compileEndif()
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
    protected function compileRepeater($values)
    {
        $values = trim($values);
        if ((!$values) || ($values == "") || (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values)))
        {
            $this->triggerError(new InvalidArgumentException("invalid repeater name: '$values'."));
            return '<?php if(1==2) { ?>';
        }

        $temp = explode(".", $values);

        if ((is_array($temp)) && (count($temp) > 1))
        {
            $thing = '$'.$temp[count($temp) - 2].'["'.$temp[count($temp) - 1].'"]';

            $returnString = '<?php $counter["'.$values.'"]= 1; foreach ('.$thing.' as $'
                                .$temp[count($temp) - 1].') { $counter["'.$values
                                .'"]= 1 - $counter["'.$values.'"]; ?>';
        }
        else
        {
            $returnString = '<?php $counter["'.$values.'"]= 1; foreach ($this->vars["'.$values
                                .'"] as $'.$values.') { $counter["'.$values.'"]= 1 - $counter["'
                                .$values.'"]; ?>';
        }

        return $returnString;
    }

    /**
     * compiles the </repeater> tags of the template into its PHP equivalent.
     *
     * @return string Compiled </repeater> tag.
     */
    protected function compileEndrepeater()
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
    protected function compileCycler($parent, $values)
    {
        $parent = trim($parent);
        if ((!$parent) || ($parent == "") || (!preg_match("/[A-Za-z0-9_\.]\w*/i", $parent)))
        {
            $this->triggerError(
                    new InvalidArgumentException("Invalid cycler parent name: '$parent'."));

            return '<?php if(1==2) { ?>';
        }

        $values = trim($values);
        if ((!$values) || ($values == "") || (!preg_match("/default|alternate/i", $values)))
        {
            $this->triggerError(new InvalidArgumentException("Invalid cycler name: '$values'."));
            return '<?php if(1==2) { ?>';
        }

        if ($values == "alternate")
        {
            $returnString = '<?php if($counter['.$parent.']==1) { ?>';
        }
        else
        {
            $returnString = '<?php if($counter['.$parent.']==0) { ?>';
        }

        return $returnString;
    }

    /**
     * compiles the </cycler> tags of the template into its PHP equivalent.
     *
     * @return string Compiled </cycler> tag.
     */
    protected function compileEndcycler()
    {
        return '<?php } ?>';
    }

    /**
     * Compiles the <cycle> tags of the template into its PHP equivalent.
     *
     * @param string $parent The name of the cycle's parent.
     * @param string $values The name of the cycle.
     *
     * @return string Compiled <cycle> tag.
     */
    protected function compileCycle($parent, $values)
    {
        $parent = trim($parent);
        if ((!$parent) || ($parent == "") || (!preg_match("/[A-Za-z0-9_\.]\w*/i", $parent)))
        {
            $this->triggerError(
                    new InvalidArgumentException("invalid cycle parent name: '$parent'."));
        }

        $values = trim($values);
        if ((!$values) || ($values == ""))
        {
            $this->triggerError(new InvalidArgumentException("invalid cycle values: '$values'."));
        }

        $returnString = '<?php echo $this->doCycle("'.$parent.'","'.$values.'"); ?>';

        return $returnString;
    }


    /**
     * compiles the <var> tags of the template into its PHP equivalent.
     *
     * @param string $values The name of the variable.
     *
     * @return string Compiled <var> tag.
     */
    protected function compileVar($values)
    {
        $values = trim($values);
        if ((!$values) || ($values == "") || (!preg_match("/[A-Za-z0-9_\.]\w*/i", $values)))
            {
            $this->triggerError(new InvalidArgumentException("invalid var name: '$values'."));
            return '';
        }

        $temp = explode(".", $values);
        if ((is_array($temp)) && (count($temp) > 1))
        {
            $thing = '$'.$temp[count($temp) - 2].'["'.$temp[count($temp) - 1].'"]';
            if ($this->safeCompiling == true)
            {
                $returnString = '<?php if (isset('.$thing.')) { echo '.$thing.'; } ?>';
            }
            else
            {
                $returnString = '<?php echo '.$thing.'; ?>';
            }
        }
        else
        {
            if ($this->safeCompiling == true)
            {
                $returnString = '<?php if (isset($this->vars["'.$values
                                    .'"])) { echo $this->vars["'.$values.'"]; } ?>';
            }
            else
            {
                $returnString = '<?php echo $this->vars["'.$values.'"]; ?>';
            }
        }

        return $returnString;

    }

    /**
     * Evaluates the compiled template to raw HTML.
     *
     * @param string $compiledTemplate The actual PHP version of the template that is to be
     *                                  evaluated.
     *
     * @return string Evaluated template.
     */
    protected function evaluate($compiledTemplate)
    {
        ob_start();
        eval("?".chr(62).$compiledTemplate.chr(60)."?php ");
        $evaled = ob_get_contents();
        ob_end_clean();

        $this->resetVars();

        return $evaled;
    }
}
