<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2002, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id: Admin.php 434 2010-07-06 12:53:16Z drak $
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_Value_Addons
 * @subpackage Pages
 */

class Pages_Controller_Admin extends Zikula_Controller
{
    /**
     * the main administration function
     *
     * @return string HTML output
     */
    public function main()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Pages::', '::', ACCESS_EDIT)) {
            return LogUtil::registerPermissionError();
        }

        // Create output object
        $this->view->setCaching(false);

        // Return the output that has been generated by this function
        return $this->view->fetch('pages_admin_main.htm');
    }

    /**
     * add new item
     *
     * @return string HTML output
     */
    public function newitem()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Pages::', '::', ACCESS_ADD)) {
            return LogUtil::registerPermissionError();
        }

        // Get the module configuration vars
        $modvars = $this->getVars();

        // Create output object
        $this->view->setCaching(false);

        if ($modvars['enablecategorization']) {
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('Pages', 'pages');

            $this->view->assign('catregistry', $catregistry);
        }

        $this->view->assign($modvars);
        $this->view->assign('lang', ZLanguage::getLanguageCode());

        // Return the output that has been generated by this function
        return $this->view->fetch('pages_admin_new.htm');
    }

    /**
     * create a page
     * @param 'title' the title of the page
     * @param 'content' the content of the page
     * @param 'language' the language of the page
     */
    public function create($args)
    {
        // Confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError (ModUtil::url('Pages', 'admin', 'view'));
        }

        $page = FormUtil::getPassedValue('page', isset($args['page']) ? $args['page'] : null, 'POST');

        // Notable by its absence there is no security check here
        // Create the page
        $pageid = ModUtil::apiFunc('Pages', 'admin', 'create', $page);

        // The return value of the function is checked
        if ($pageid != false) {
            // Success
            LogUtil::registerStatus($this->__('Done! Page created.'));
        }

        return System::redirect(ModUtil::url('Pages', 'admin', 'view'));
    }

    /**
     * modify a page
     *
     * @param 'pageid' the id of the item to be modified
     * @return string HTML output
     */
    public function modify($args)
    {
        $pageid   = FormUtil::getPassedValue('pageid', isset($args['pageid']) ? $args['pageid'] : null, 'GET');
        $objectid = FormUtil::getPassedValue('objectid', isset($args['objectid']) ? $args['objectid'] : null, 'GET');
        // At this stage we check to see if we have been passed $objectid
        if (!empty($objectid)) {
            $pageid = $objectid;
        }

        // Validate the essential parameters
        if (empty($pageid)) {
            return LogUtil::registerArgsError();
        }

        // Get the page
        $item = ModUtil::apiFunc('Pages', 'user', 'get', array('pageid' => $pageid));

        if ($item === false) {
            return LogUtil::registerError($this->__('No such page found.'), 404);
        }

        // Security check
        if (!SecurityUtil::checkPermission('Pages::', "$item[title]::$pageid", ACCESS_EDIT)) {
            return LogUtil::registerPermissionError();
        }

        // Get the module configuration vars
        $modvars = $this->getVars();

        $item['returnurl'] = System::serverGetVar('HTTP_REFERER');

        // Create output object
        $this->view->setCaching(false);

        if ($modvars['enablecategorization']) {
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('Pages', 'pages');

            $this->view->assign('catregistry', $catregistry);
        }

        // assign the item to the template
        $this->view->assign($item);
        $this->view->assign($modvars);

        // now we've got this far let's lock the page for editing
        ModUtil::apiFunc('PageLock', 'user', 'pageLock',
                array('lockName' => "Pagespage{$pageid}",
                'returnUrl' => ModUtil::url('Pages', 'admin', 'view')));

        // Return the output that has been generated by this function
        return $this->view->fetch('pages_admin_modify.htm');
    }

    /**
     * update page
     *
     * @param 'pageid' the id of the page
     * @param 'title' the title of the page
     * @param 'content' the content of the page
     * @param 'language' the language of the page
     */
    public function update($args)
    {
        // Confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Pages', 'admin', 'view'));
        }

        $page = FormUtil::getPassedValue('page', isset($args['page']) ? $args['page'] : null, 'POST');
        $url  = FormUtil::getPassedValue('url', isset($args['url']) ? $args['url'] : null, 'POST');
        if (!empty($page['objectid'])) {
            $page['pageid'] = $page['objectid'];
        }

        // Validate the essential parameters
        if (empty($page['pageid'])) {
            return LogUtil::registerArgsError();
        }

        // Notable by its absence there is no security check here
        // Update the page
        if (ModUtil::apiFunc('Pages', 'admin', 'update', $page)) {
            // Success
            LogUtil::registerStatus($this->__('Done! Page updated.'));
        }

        // now release the page lock
        ModUtil::apiFunc('PageLock', 'user', 'releaseLock',
                array('lockName' => "Pagespage{$page['pageid']}"));

        if (!isset($url)) {
            return System::redirect(ModUtil::url('Pages', 'admin', 'view'));
        }

        return System::redirect($url);
    }

    /**
     * delete item
     *
     * @param 'pageid' the id of the page
     * @param 'confirmation' confirmation that this item can be deleted
     * @return mixed string HTML output if no confirmation otherwise true
     */
    public function delete($args)
    {
        $pageid = FormUtil::getPassedValue('pageid', isset($args['pageid']) ? $args['pageid'] : null, 'REQUEST');
        $objectid = FormUtil::getPassedValue('objectid', isset($args['objectid']) ? $args['objectid'] : null, 'REQUEST');
        $confirmation = FormUtil::getPassedValue('confirmation', null, 'POST');
        if (!empty($objectid)) {
            $pageid = $objectid;
        }

        // Validate the essential parameters
        if (empty($pageid)) {
            return LogUtil::registerArgsError();
        }

        // Get the existing page
        $item = ModUtil::apiFunc('Pages', 'user', 'get', array('pageid' => $pageid));

        if ($item === false) {
            return LogUtil::registerError($this->__('No such page found.'), 404);
        }

        // Security check
        if (!SecurityUtil::checkPermission('Pages::', "$item[title]::$pageid", ACCESS_DELETE)) {
            return LogUtil::registerPermissionError();
        }

        // Check for confirmation.
        if (empty($confirmation)) {
            // No confirmation yet
            // Create output object
            $this->view->setCaching(false);

            // Add a hidden field for the item ID to the output
            $this->view->assign('pageid', $pageid);

            // Return the output that has been generated by this function
            return $this->view->fetch('pages_admin_delete.htm');
        }

        // If we get here it means that the user has confirmed the action

        // Confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Pages', 'admin', 'view'));
        }

        // Delete the page
        if (ModUtil::apiFunc('Pages', 'admin', 'delete', array('pageid' => $pageid))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Page deleted.'));
        }

        return System::redirect(ModUtil::url('Pages', 'admin', 'view'));
    }

    /**
     * view items
     *
     * @param int $startnum the start item id for the pager
     * @return string HTML output
     */
    public function view($args)
    {
        // Security check
        if (!SecurityUtil::checkPermission('Pages::', '::', ACCESS_EDIT)) {
            return LogUtil::registerPermissionError();
        }

        // Get parameters from whatever input we need.
        $startnum = (int)FormUtil::getPassedValue('startnum', isset($args['startnum']) ? $args['startnum'] : null, 'GET');
        $language = FormUtil::getPassedValue('language', isset($args['language']) ? $args['language'] : null, 'POST');
        $property = FormUtil::getPassedValue('pages_property', isset($args['pages_property']) ? $args['pages_property'] : null, 'POST');
        $category = FormUtil::getPassedValue("pages_{$property}_category", isset($args["pages_{$property}_category"]) ? $args["pages_{$property}_category"] : null, 'POST');
        $clear    = FormUtil::getPassedValue('clear', false, 'POST');
        $purge    = FormUtil::getPassedValue('purge', false, 'GET');

        if ($purge) {
            if (ModUtil::apiFunc('Pages', 'admin', 'purgepermalinks')) {
                LogUtil::registerStatus($this->__('Purging of the pemalinks was successful'));
            } else {
                LogUtil::registerError($this->__('Purging of the pemalinks has failed'));
            }
            return System::redirect(strpos(System::serverGetVar('HTTP_REFERER'), 'purge') ? ModUtil::url('Pages', 'admin', 'view') : System::serverGetVar('HTTP_REFERER'));
        }
        if ($clear) {
            $property = null;
            $category = null;
        }

        // get module vars for later use
        $modvars = $this->getVars();

        if ($modvars['enablecategorization']) {
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('Pages', 'pages');
            $properties  = array_keys($catregistry);

            // Validate and build the category filter - mateo
            if (!empty($property) && in_array($property, $properties) && !empty($category)) {
                $catFilter = array($property => $category);
            }

            // Assign a default property - mateo
            if (empty($property) || !in_array($property, $properties)) {
                $property = $properties[0];
            }

            // plan ahead for ML features
            $propArray = array();
            foreach ($properties as $prop) {
                $propArray[$prop] = $prop;
            }
        }

        $multilingual = System::getVar('multilingual', false);

        // Get all matching pages
        $items = ModUtil::apiFunc('Pages', 'user', 'getall',
                array('startnum' => $startnum,
                'numitems' => $modvars['itemsperpage'],
                'order'    => 'pageid',
                'ignoreml' => ($multilingual ? false : true),
                'language' => $language,
                'category' => isset($catFilter) ? $catFilter : null,
                'catregistry'  => isset($catregistry) ? $catregistry : null));

        if (!$items) {
            $items = array();
        }

        $pages = array();
        foreach ($items as $key => $item)
        {
            $options = array();
            $options[] = array('url'   => ModUtil::url('Pages', 'user', 'display', array('pageid' => $item['pageid'])),
                    'image' => 'demo.gif',
                    'title' => $this->__('View'));

            if (SecurityUtil::checkPermission('Pages::', "$item[title]::$item[pageid]", ACCESS_EDIT)) {
                $options[] = array('url'   => ModUtil::url('Pages', 'admin', 'modify', array('pageid' => $item['pageid'])),
                        'image' => 'xedit.gif',
                        'title' => $this->__('Edit'));

                if (SecurityUtil::checkPermission('Pages::', "$item[title]::$item[pageid]", ACCESS_DELETE)) {
                    $options[] = array('url'   => ModUtil::url('Pages', 'admin', 'delete', array('pageid' => $item['pageid'])),
                            'image' => '14_layer_deletelayer.gif',
                            'title' => $this->__('Delete'));
                }
            }

            // Add the calculated menu options to the item array
            $item['options'] = $options;
            $pages[] = $item;
        }

        $this->view->setCaching(false);

        // Assign the items to the template
        $this->view->assign('pages', $pages);
        $this->view->assign($modvars);

        // Assign the default language
        $this->view->assign('lang', ZLanguage::getLanguageCode());
        $this->view->assign('language', $language);

        // Assign the categories information if enabled
        if ($modvars['enablecategorization']) {
            $this->view->assign('catregistry', $catregistry);
            $this->view->assign('numproperties', count($propArray));
            $this->view->assign('properties', $propArray);
            $this->view->assign('property', $property);
            $this->view->assign('category', $category);
        }

        // Assign the information required to create the pager
        $this->view->assign('pager', array('numitems'     => ModUtil::apiFunc('Pages', 'user', 'countitems', array('category' => isset($catFilter) ? $catFilter : null)),
                'itemsperpage' => $modvars['itemsperpage']));

        // Return the output that has been generated by this function
        return $this->view->fetch('pages_admin_view.htm');
    }

    /**
     * modify module configuration
     *
     * @author Mark West
     * @return string HTML output string
     */
    public function modifyconfig()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Pages::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        $this->view->setCaching(false);

        // Assign all module vars
        $this->view->assign($this->getVars());

        // Return the output that has been generated by this function
        return $this->view->fetch('pages_admin_modifyconfig.htm');
    }

    /**
     * This is a standard function to update the configuration parameters of the
     * module given the information passed back by the modification form
     */
    public function updateconfig()
    {
        // Security check
        if (!SecurityUtil::checkPermission('Pages::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        // Confirm authorisation code
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(ModUtil::url('Pages', 'admin', 'view'));
        }

        // Update module variables
        $itemsperpage = (int)FormUtil::getPassedValue('itemsperpage', 25, 'POST');
        if ($itemsperpage < 1) {
            $itemsperpage = 25;
        }
        $this->setVar('itemsperpage', $itemsperpage);

        $enablecategorization = (bool)FormUtil::getPassedValue('enablecategorization', false, 'POST');
        $this->setVar('enablecategorization', $enablecategorization);

        $def_displaywrapper = (bool)FormUtil::getPassedValue('def_displaywrapper', false, 'POST');
        $this->setVar('def_displaywrapper', $def_displaywrapper);

        $def_displaytitle = (bool)FormUtil::getPassedValue('def_displaytitle', false, 'POST');
        $this->setVar('def_displaytitle', $def_displaytitle);

        $def_displaycreated = (bool)FormUtil::getPassedValue('def_displaycreated', false, 'POST');
        $this->setVar('def_displaycreated', $def_displaycreated);

        $def_displayupdated = (bool)FormUtil::getPassedValue('def_displayupdated', false, 'POST');
        $this->setVar('def_displayupdated', $def_displayupdated);

        $def_displaytextinfo = (bool)FormUtil::getPassedValue('def_displaytextinfo', false, 'POST');
        $this->setVar('def_displaytextinfo', $def_displaytextinfo);

        $def_displayprint = (bool)FormUtil::getPassedValue('def_displayprint', false, 'POST');
        $this->setVar('def_displayprint', $def_displayprint);

        $addcategorytitletopermalink = (bool)FormUtil::getPassedValue('addcategorytitletopermalink', false, 'POST');
        $this->setVar('addcategorytitletopermalink', $addcategorytitletopermalink);

        $showpermalinkinput = (bool)FormUtil::getPassedValue('showpermalinkinput', false, 'POST');
        $this->setVar('showpermalinkinput', $showpermalinkinput);

        // Let any other modules know that the modules configuration has been updated
        $this->callHooks('module','updateconfig','Pages', array('module' => 'Pages'));

        // the module configuration has been updated successfuly
        LogUtil::registerStatus($this->__('Done! Module configuration updated.'));

        return System::redirect(ModUtil::url('Pages', 'admin', 'view'));
    }
}