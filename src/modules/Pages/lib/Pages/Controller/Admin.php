<?php
class Pages_Controller_Admin extends Zikula_AbstractController
{
    /**
     * the main administration function
     *
     * @return string HTML output
     */
    public function main()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Pages::', '::', ACCESS_EDIT), LogUtil::getErrorMsgPermission());
        // Create output object
        $this->view->setCaching(false);
		$this->redirect(ModUtil::url('Pages', 'admin', 'view'));
    }

    /**
     * add new item
     *
     * @return string HTML output
     */
    public function newitem()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Pages::', '::', ACCESS_ADD), LogUtil::getErrorMsgPermission());

        // Get the module configuration vars
        $modvars = $this->getVars();

        // Create output object
        $this->view->setCaching(false);

        if ($modvars['enablecategorization']) {
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('Pages', 'pages');

            $this->view->assign('catregistry', $catregistry);
        }

        $this->view->assign('lang', ZLanguage::getLanguageCode());

        // Return the output that has been generated by this function
        return $this->view->fetch('admin/new.tpl');
    }

    /**
     * create a page
     * @param 'title' the title of the page
     * @param 'content' the content of the page
     * @param 'language' the language of the page
     */
    public function create($args)
    {
        $this->checkCsrfToken();

        $page = FormUtil::getPassedValue('page', isset($args['page']) ? $args['page'] : null, 'POST');

        $validators = $this->notifyHooks('pages.hook.pages.validate.edit', $page, null, array(), new Zikula_Hook_ValidationProviders())->getData();
        if (!$validators->hasErrors()) {
            $pageid = ModUtil::apiFunc('Pages', 'admin', 'create', $page);
            if ($pageid != false) {
                $this->notifyHooks('pages.hook.pages.process.edit', $page, $pageid);
                LogUtil::registerStatus($this->__('Done! Page created.'));
            }
        } else {
            LogUtil::registerError($this->__('Error! Hook data did not validate. Page not created.'));
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

        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Pages::', $item['title'] . '::' . $pageid, ACCESS_EDIT), LogUtil::getErrorMsgPermission());

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
        $this->view->assign('item', $item);

        // now we've got this far let's lock the page for editing
        ModUtil::apiFunc('PageLock', 'user', 'pageLock',
                array('lockName' => "Pagespage{$pageid}",
                'returnUrl' => ModUtil::url('Pages', 'admin', 'view')));

        // Return the output that has been generated by this function
        return $this->view->fetch('admin/modify.tpl');
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
        $this->checkCsrfToken();

        $page = FormUtil::getPassedValue('page', isset($args['page']) ? $args['page'] : null, 'POST');
        $url  = FormUtil::getPassedValue('url', isset($args['url']) ? $args['url'] : null, 'POST');
        if (!empty($page['objectid'])) {
            $page['pageid'] = $page['objectid'];
        }

        // Validate the essential parameters
        if (empty($page['pageid'])) {
            return LogUtil::registerArgsError();
        }

        $validators = $this->notifyHooks('pages.hook.pages.validate.edit', $page, $page['pageid'], array(), new Zikula_Hook_ValidationProviders())->getData();
        if (!$validators->hasErrors()) {
            if (ModUtil::apiFunc('Pages', 'admin', 'update', $page)) {
                // Success
                LogUtil::registerStatus($this->__('Done! Page updated.'));
                $this->notifyHooks('pages.hook.pages.process.edit', $page, $page['pageid']);
            }
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

        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Pages::', $item['title'] . '::' . $pageid, ACCESS_DELETE), LogUtil::getErrorMsgPermission());

        // Check for confirmation.
        if (empty($confirmation)) {
            // No confirmation yet
            // Create output object
            $this->view->setCaching(false);

            // Add a hidden field for the item ID to the output
            $this->view->assign('pageid', $pageid);

            // Return the output that has been generated by this function
            return $this->view->fetch('admin/delete.tpl');
        }

        // If we get here it means that the user has confirmed the action

        $this->checkCsrfToken();

        // Delete the page
        if (ModUtil::apiFunc('Pages', 'admin', 'delete', array('pageid' => $pageid))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Page deleted.'));
            $this->notifyHooks('pages.hook.pages.process.delete', $item, $pageid);
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
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Pages::', '::', ACCESS_EDIT), LogUtil::getErrorMsgPermission());

        // initialize sort array - used to display sort classes and urls
        $sort = array();
        $fields = array('pageid', 'title', 'cr_date'); // possible sort fields
        foreach ($fields as $field) {
            $sort['class'][$field] = 'z-order-unsorted'; // default values
        }
        
        // Get parameters from whatever input we need.
        $startnum = (int)FormUtil::getPassedValue('startnum', isset($args['startnum']) ? $args['startnum'] : null, 'GETPOST');
        $language = FormUtil::getPassedValue('language', isset($args['language']) ? $args['language'] : null, 'POST');
        $purge = FormUtil::getPassedValue('purge', false, 'GET');
        $orderby = FormUtil::getPassedValue('orderby', isset($args['orderby']) ? $args['orderby'] : 'pageid', 'GETPOST');
        $original_sdir = FormUtil::getPassedValue('sdir', isset($args['sdir']) ? $args['sdir'] : 1, 'GETPOST');

        $this->view->assign('startnum', $startnum);
        $this->view->assign('orderby', $orderby);
        $this->view->assign('sdir', $original_sdir);

        $sdir = $original_sdir ? 0 : 1; //if true change to false, if false change to true
        // change class for selected 'orderby' field to asc/desc
        if ($sdir == 0) {
            $sort['class'][$orderby] = 'z-order-desc';
            $orderdir = 'DESC';
        }
        if ($sdir == 1) {
            $sort['class'][$orderby] = 'z-order-asc';
            $orderdir = 'ASC';
        }
        $filtercats = FormUtil::getPassedValue('pages', null, 'GETPOST');
        $filtercats_serialized = FormUtil::getPassedValue('filtercats_serialized', false, 'GET');
        $filtercats = $filtercats_serialized ? unserialize($filtercats_serialized) : $filtercats;
        $catsarray = Pages_Util::formatCategoryFilter($filtercats);

        // complete initialization of sort array, adding urls
        foreach ($fields as $field) {
            $sort['url'][$field] = ModUtil::url('Pages', 'admin', 'view', array(
                'language' => $language,
                'filtercats_serialized' => serialize($filtercats),
                'orderby' => $field,
                'sdir' => $sdir));
        }
        $this->view->assign('sort', $sort);

        $this->view->assign('filter_active', (empty($language) && empty($catsarray)) ? false : true);

        if ($purge) {
            if (ModUtil::apiFunc('Pages', 'admin', 'purgepermalinks')) {
                LogUtil::registerStatus($this->__('Purging of the pemalinks was successful'));
            } else {
                LogUtil::registerError($this->__('Purging of the pemalinks has failed'));
            }
            return System::redirect(strpos(System::serverGetVar('HTTP_REFERER'), 'purge') ? ModUtil::url('Pages', 'admin', 'view') : System::serverGetVar('HTTP_REFERER'));
        }

        // get module vars
        $modvars = $this->getVars();

        if ($modvars['enablecategorization']) {
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('Pages', 'pages');
            $this->view->assign('catregistry', $catregistry);
        }

        $multilingual = System::getVar('multilingual', false);

        // Get all matching pages
        $items = ModUtil::apiFunc('Pages', 'user', 'getall',
                array('startnum' => $startnum,
                'numitems' => $modvars['itemsperpage'],
                'order'    => $orderby,
                'orderdir' => $orderdir,
                'ignoreml' => ($multilingual ? false : true),
                'language' => $language,
                'category' => null,
                'catfilter' => isset($catsarray) ? $catsarray : null,
                'catregistry'  => isset($catregistry) ? $catregistry : null));

        if (!$items) {
            $items = array();
        }

        $pages = array();
        foreach ($items as $key => $item)
        {
            $options = array();
            $options[] = array('url'   => ModUtil::url('Pages', 'user', 'display', array('pageid' => $item['pageid'])),
                    'image' => 'kview.png',
                    'title' => $this->__('View'));

            if (SecurityUtil::checkPermission('Pages::', "$item[title]::$item[pageid]", ACCESS_EDIT)) {
                $options[] = array('url'   => ModUtil::url('Pages', 'admin', 'modify', array('pageid' => $item['pageid'])),
                        'image' => 'xedit.png',
                        'title' => $this->__('Edit'));

                if (SecurityUtil::checkPermission('Pages::', "$item[title]::$item[pageid]", ACCESS_DELETE)) {
                    $options[] = array('url'   => ModUtil::url('Pages', 'admin', 'delete', array('pageid' => $item['pageid'])),
                            'image' => '14_layer_deletelayer.png',
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

        // Assign the default language
        $this->view->assign('lang', ZLanguage::getLanguageCode());
        $this->view->assign('language', $language);

        // Assign the information required to create the pager
        $this->view->assign('pager', array(
            'numitems' => ModUtil::apiFunc('Pages', 'user', 'countitems', array('catfilter' => isset($catsarray) ? $catsarray : null)),
            'itemsperpage' => $modvars['itemsperpage']));

        $selectedcategories = array();
        if (is_array($filtercats)) {
            $catsarray = $filtercats['__CATEGORIES__'];
            foreach ($catsarray as $propname => $propid) {
                if ($propid > 0) {
                    $selectedcategories[$propname] = $propid; // removes categories set to 'all'
                }
            }
        }
        $this->view->assign('selectedcategories', $selectedcategories);
        
        // Return the output that has been generated by this function
        return $this->view->fetch('admin/view.tpl');
    }

    /**
     * modify module configuration
     *
     * @author Mark West
     * @return string HTML output string
     */
    public function modifyconfig()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Pages::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        $this->view->setCaching(false);

        // Return the output that has been generated by this function
        return $this->view->fetch('admin/modifyconfig.tpl');
    }

    /**
     * This is a standard function to update the configuration parameters of the
     * module given the information passed back by the modification form
     */
    public function updateconfig()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Pages::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        $this->checkCsrfToken();

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

        // the module configuration has been updated successfuly
        LogUtil::registerStatus($this->__('Done! Module configuration updated.'));

        return System::redirect(ModUtil::url('Pages', 'admin', 'view'));
    }
}