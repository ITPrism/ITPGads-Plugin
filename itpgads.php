<?php
/**
 * @package         ITPGads
 * @subpackage      Plugins
 * @copyright       Copyright (C) 2014 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license         http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

class plgContentITPGads extends JPlugin
{
    /**
     * A JRegistry object holding the parameters for the plugin
     *
     * @var    Joomla\Registry\Registry
     * @since  1.5
     */
    public $params = null;

    private $currentView = "";
    private $currentTask = "";
    private $currentOption = "";
    private $currentLayout = "";

    /**
     * Generate and include Google AdSense code to the page content.
     *
     * Method is called by the view
     *
     * @param   string                   $context    The context of the content being passed to the plugin.
     * @param   object                   $article    The content object.  Note $article->text is also available
     * @param   Joomla\Registry\Registry $params     The content params
     * @param   int                      $limitstart The 'page' number
     *
     * @return void
     * @since   1.6
     */
    public function onContentPrepare($context, &$article, &$params, $limitstart)
    {
        if (!$article or !isset($this->params) or empty($article->text)) {
            return;
        }

        $options = array("on_content_prepare", "on_content_prepare_indicator");
        $option  = $this->params->get("trigger_place");

        // Check for correct trigger
        if (!in_array($option, $options)) {
            return;
        }

        // Generate content
        $content  = $this->processGenerating($context, $article, $params, $page = 0);

        // If there is no result, return void.
        if (is_null($content)) {
            return;
        }

        if (strcmp($option, "on_content_prepare_indicator") == 0) { // Replace indicator with ad code

            $article->text = str_replace("{itpgads}", $content, $article->text);

        } else {

            $position = $this->params->get('position');

            switch ($position) {
                case 1:
                    $article->text = $content . $article->text;
                    break;
                case 2:
                    $article->text = $article->text . $content;
                    break;
                default:
                    $article->text = $content . $article->text . $content;
                    break;
            }
        }

    }

    /**
     * Generate and include Google AdSense code before content.
     *
     * @param    string                   $context The context of the content being passed to the plugin.
     * @param    object                   $article The article object.  Note $article->text is also available
     * @param    Joomla\Registry\Registry $params  The article params
     * @param    int                      $page    The 'page' number
     *
     * @return string
     */
    public function onContentBeforeDisplay($context, &$article, &$params, $page = 0)
    {
        // Check for correct trigger
        if (strcmp("on_content_before_display", $this->params->get("trigger_place")) != 0) {
            return "";
        }

        return $this->processGenerating($context, $article, $params, $page = 0);
    }

    /**
     * Generate and include Google AdSense code after content.
     *
     * @param    string                   $context The context of the content being passed to the plugin.
     * @param    object                   $article The article object.  Note $article->text is also available
     * @param    Joomla\Registry\Registry $params  The article params
     * @param    int                      $page    The 'page' number
     *
     * @return string
     */
    public function onContentAfterDisplay($context, &$article, &$params, $page = 0)
    {
        // Check for correct trigger
        if (strcmp("on_content_after_display", $this->params->get("trigger_place")) != 0) {
            return "";
        }

        return $this->processGenerating($context, $article, $params, $page = 0);
    }


    /**
     * Execute the process of buttons generating.
     *
     * @param string                   $context
     * @param object                   $article
     * @param Joomla\Registry\Registry $params
     * @param int                      $page
     *
     * @return null|string
     */
    private function processGenerating($context, &$article, &$params, $page = 0)
    {
        if (!$article or !isset($this->params)) {
            return null;
        };

        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        if ($app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp("html", $docType) != 0) {
            return null;
        }

        // Get request data
        $this->currentOption = $app->input->getCmd("option");
        $this->currentView   = $app->input->getCmd("view");
        $this->currentTask   = $app->input->getCmd("task");
        $this->currentLayout = $app->input->getCmd("layout");

        if ($this->isRestricted($article, $context, $params)) {
            return null;
        }

        // Generate and return content
        return $this->getContent($article, $context);
    }

    /**
     * Generate content
     *
     * @param   object $article The article object.  Note $article->text is also available
     * @param   string $context The article params
     *
     * @return  string      Returns html code or empty string.
     */
    private function getContent(&$article, $context)
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        // Get blocked IP addresses
        $ips = explode(",", $this->params->get('blockedIPs'));
        foreach ($ips as &$ip) {
            $ip = trim($ip);
        }

        $remoteAddress = $app->input->server->get("REMOTE_ADDR");
        if (in_array($remoteAddress, $ips)) {
            return '<div style="clear:both;">' . $this->params->get('altMessage') . '</div>';
        }

        // Get custom code
        $customCode = $this->params->get('custom_code');

        if (!empty($customCode)) { // Display the custom code

            $html = '<div style="clear:both;">';
            $html .= $customCode;
            $html .= '</div>';

            return $html;

        }

        /* Let's show the ad */
        $publisherId = $this->params->get('publisherId');
        $slotId      = $this->params->get('slot');

        // Get size
        $adFormat = $this->params->get('format');
        $format   = explode("-", $adFormat);
        $width    = explode("x", $format[0]);
        $height   = explode("_", $width[1]);

        if ($this->params->get("ad_type", 1)) { // Asynchronous

            return '<div><script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<ins class="adsbygoogle"
     style="display:inline-block;width:' . $width[0] . 'px;height:' . $height[0] . 'px"
     data-ad-client="' . $publisherId . '"
     data-ad-slot="' . $slotId . '"></ins>
<script>
(adsbygoogle = window.adsbygoogle || []).push({});
</script></div>';

        } else { // Synchronous

            return '<div><script type="text/javascript"><!--
google_ad_client = "' . $publisherId . '";
google_ad_slot = "' . $slotId . '";
google_ad_width = ' . $width[0] . ';
google_ad_height = ' . $height[0] . ';
//-->
</script>
<script type="text/javascript"
src="//pagead2.googlesyndication.com/pagead/show_ads.js">
</script></div>';

        }

    }

    /**
     * Validate the possibility to be loaded this plugin in some extensions.
     *
     * @param object $article
     * @param string $context
     *
     * @return boolean
     */
    private function isRestricted($article, $context)
    {
        switch ($this->currentOption) {
            case "com_content":
                $result = $this->isContentRestricted($article, $context);
                break;

            case "com_k2":
                $result = $this->isK2Restricted($article, $context);
                break;

            case "com_virtuemart":
                $result = $this->isVirtuemartRestricted($context);
                break;

            case "com_jevents":
                $result = $this->isJEventsRestricted($article, $context);
                break;

            case "com_vipportfolio":
                $result = $this->isVipPortfolioRestricted($context);
                break;

            case "com_zoo":
                $result = $this->isZooRestricted($context);
                break;

            case "com_jshopping":
                $result = $this->isJoomShoppingRestricted($article, $context);
                break;

            case "com_hikashop":
                $result = $this->isHikaShopRestricted($context);
                break;

            case "com_vipquotes":
                $result = $this->isVipQuotesRestricted($context);
                break;
            default:
                $result = true;
                break;
        }

        return $result;
    }

    /**
     * Checks allowed articles, excluded categories/articles,... for component COM_CONTENT
     *
     * @param object $article
     * @param string $context
     *
     * @return bool
     */
    private function isContentRestricted(&$article, $context)
    {
        // Check for correct context
        if ((false === strpos($context, "com_content")) or empty($article->id)) {
            return true;
        }

        /** Check for selected views, which will display the buttons. **/
        /** If there is a specific set and do not match, return an empty string.**/
        $showInArticles = $this->params->get('showInArticles');
        if (!$showInArticles and (strcmp("article", $this->currentView) == 0)) {
            return true;
        }

        // Will be displayed in view "categories"?
        $showInCategories = $this->params->get('showInCategories');
        if (!$showInCategories and (strcmp("category", $this->currentView) == 0)) {
            return true;
        }

        // Will be displayed in view "featured"?
        $showInFeatured = $this->params->get('showInFeatured');
        if (!$showInFeatured and (strcmp("featured", $this->currentView) == 0)) {
            return true;
        }

        // Exclude articles
        $excludeArticles = $this->params->get('excludeArticles');
        if (!empty($excludeArticles)) {
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        JArrayHelper::toInteger($excludeArticles);

        // Excluded categories
        $excludedCats = $this->params->get('excludeCats');
        if (!empty($excludedCats)) {
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        JArrayHelper::toInteger($excludedCats);

        // Included Articles
        $includedArticles = $this->params->get('includeArticles');
        if (!empty($includedArticles)) {
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        JArrayHelper::toInteger($includedArticles);

        if (!in_array($article->id, $includedArticles)) {
            // Check exluded articles
            if (in_array($article->id, $excludeArticles) or in_array($article->catid, $excludedCats)) {
                return true;
            }
        }

        return false;
    }

    /**
     * This method does verification for K2 restrictions.
     *
     * @param object $article
     * @param string $context
     *
     * @return bool
     */
    private function isK2Restricted(&$article, $context)
    {
        // Check for correct context
        if (strpos($context, "com_k2") === false) {
            return true;
        }

        if ($article instanceof TableK2Category) {
            return true;
        }

        $displayInItemlist = $this->params->get('k2DisplayInItemlist', 0);
        if (!$displayInItemlist and (strcmp("itemlist", $this->currentView) == 0)) {
            return true;
        }

        $displayInArticles = $this->params->get('k2DisplayInArticles', 0);
        if (!$displayInArticles and (strcmp("item", $this->currentView) == 0)) {
            return true;
        }

        // Exclude articles
        $excludeArticles = $this->params->get('k2_exclude_articles');
        if (!empty($excludeArticles)) {
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        JArrayHelper::toInteger($excludeArticles);

        // Exluded categories
        $excludedCats = $this->params->get('k2_exclude_cats');
        if (!empty($excludedCats)) {
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        JArrayHelper::toInteger($excludedCats);

        // Included Articles
        $includedArticles = $this->params->get('k2_include_articles');
        if (!empty($includedArticles)) {
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        JArrayHelper::toInteger($includedArticles);

        if (!in_array($article->id, $includedArticles)) {
            // Check excluded articles
            if (in_array($article->id, $excludeArticles) or in_array($article->catid, $excludedCats)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Do verifications for JEvent extension
     *
     * @param object $article
     * @param string $context
     *
     * @return bool
     */
    private function isJEventsRestricted(&$article, $context)
    {
        // Display buttons only in the description
        if (!is_a($article, "jIcalEventRepeat")) {
            return true;
        };

        // Check for correct context
        if (strpos($context, "com_jevents") === false) {
            return true;
        }

        // Display only in task 'icalrepeat.detail'
        if (strcmp("icalrepeat.detail", $this->currentTask) != 0) {
            return true;
        }

        $displayInEvents = $this->params->get('jeDisplayInEvents', 0);
        if (!$displayInEvents) {
            return true;
        }

        return false;
    }

    /**
     * This method does verification for VirtueMart restrictions
     *
     * @param string $context
     *
     * @return bool
     */
    private function isVirtuemartRestricted($context)
    {
        // Check for correct context
        if (strpos($context, "com_virtuemart") === false) {
            return true;
        }

        // Display content only in the view "productdetails"
        if (strcmp("productdetails", $this->currentView) != 0) {
            return true;
        }

        // Display content only in the view "productdetails"
        $displayInDetails = $this->params->get('vmDisplayInDetails', 0);
        if (!$displayInDetails) {
            return true;
        }

        return false;
    }

    /**
     * It's a method that verify restriction for the component "com_vipportfolio"
     *
     * @param string $context
     *
     * @return bool
     */
    private function isVipPortfolioRestricted($context)
    {
        // Check for correct context
        if (strpos($context, "com_vipportfolio") === false) {
            return true;
        }

        // Verify the option for displaying in layout "lineal"
        $displayInLineal = $this->params->get('vipportfolio_lineal', 0);
        if (!$displayInLineal) {
            return true;
        }

        return false;
    }

    /**
     * It's a method that verify restriction for the component "com_zoo"
     *
     * @param string $context
     *
     * @return bool
     */
    private function isZooRestricted($context)
    {
        // Check for correct context
        if (false === strpos($context, "com_zoo")) {
            return true;
        }

        // Verify the option for displaying in view "item"
        $displayInItem = $this->params->get('zoo_display', 0);
        if (!$displayInItem) {
            return true;
        }

        // Check for valid view or task
        // I have check for task because if the user comes from view category, the current view is "null" and the current task is "item"
        if ((strcmp("item", $this->currentView) != 0) and (strcmp("item", $this->currentTask) != 0)) {
            return true;
        }

        // A little hack used to prevent multiple displaying of buttons, becaues
        // if there is more than one textares the buttons will be displayed in everyone.
        static $numbers = 0;
        if ($numbers == 1) {
            return true;
        }
        $numbers = 1;

        return false;
    }

    /**
     * It's a method that verify restriction for the component "com_joomshopping"
     *
     * @param object $article
     * @param string $context
     *
     * @return bool
     */
    private function isJoomShoppingRestricted(&$article, $context)
    {
        // Check for correct context
        if (false === strpos($context, "com_content.article")) {
            return true;
        }

        // Check for enabled functionality for that extension
        $displayInDetails = $this->params->get('joomshopping_display', 0);
        if (!$displayInDetails or !isset($article->product_id)) {
            return true;
        }

        return false;
    }

    /**
     * It's a method that verify restriction for the component "com_hikashop"
     *
     * @param string $context
     *
     * @return bool
     */
    private function isHikaShopRestricted($context)
    {
        // Check for correct context
        if (false === strpos($context, "text")) {
            return true;
        }

        // Display content only in the view "product"
        if (strcmp("product", $this->currentView) != 0) {
            return true;
        }

        // Check for enabled functionality for that extension
        $displayInDetails = $this->params->get('hikashop_display', 0);
        if (!$displayInDetails) {
            return true;
        }

        return false;
    }

    /**
     * Do verification for Vip Quotes extension. Is it restricted?
     *
     * @param string $context
     *
     * @return bool
     */
    private function isVipQuotesRestricted($context)
    {
        // Check for correct context
        if (strpos($context, "com_vipquotes") === false) {
            return true;
        }

        // Display only in view 'quote'
        $allowedViews = array("author", "quote");
        if (!in_array($this->currentView, $allowedViews)) {
            return true;
        }

        $displayOnViewQuote = $this->params->get('vipquotes_display_quote', 0);
        if (!$displayOnViewQuote) {
            return true;
        }

        $displayOnViewAuthor = $this->params->get('vipquotes_display_author', 0);
        if (!$displayOnViewAuthor) {
            return true;
        }

        return false;
    }
}
