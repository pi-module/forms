<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt New BSD License
 */

/**
 * @author Hossein Azizabadi <azizabadi@faragostaresh.com>
 */

namespace Module\Forms\Controller\Front;

use Module\Forms\Form\ViewFilter;
use Module\Forms\Form\ViewForm;
use Pi;
use Pi\Mvc\Controller\ActionController;

class IndexController extends ActionController
{
    public function indexAction()
    {
        // Get info
        $module = $this->params('module');

        // Get Module Config
        $config = Pi::service('registry')->config->read($module);

        // Get form list
        $forms = Pi::api('form', 'forms')->getFormList();

        // Set template
        $this->view()->setTemplate('form-index');
        $this->view()->assign('config', $config);
        $this->view()->assign('forms', $forms);
    }

    public function viewAction()
    {
        // Get info
        $module = $this->params('module');
        $slug   = $this->params('slug');

        // Get Module Config
        $config = Pi::service('registry')->config->read($module);

        // Get uid
        $uid = Pi::user()->getId();

        // Get form
        $singleForm = Pi::api('form', 'forms')->getForm($slug, 'slug');

        // Check form
        if (!$singleForm || $singleForm['status'] != 1) {
            $this->getResponse()->setStatusCode(403);
            $this->terminate(__('The form not found.'), '', 'error-denied');
            $this->view()->setLayout('layout-simple');
            return;
        }

        // Check login in
        if ($singleForm['register_need'] == 1) {
            Pi::service('authentication')->requireLogin();
        }

        // Check form time
        if ($singleForm['time_start'] > time() || $singleForm['time_end'] < time()) {
            $this->getResponse()->setStatusCode(403);
            $this->terminate(__('You not allowed to fill this form.'), '', 'error-denied');
            $this->view()->setLayout('layout-simple');
            return;
        }

        // Get view
        $elements = Pi::api('form', 'forms')->getView($singleForm['id']);

        // Set option
        $option             = [];
        $option['elements'] = $elements;

        // Set form
        $form = new ViewForm('link', $option);
        $form->setAttribute('enctype', 'multipart/form-data');
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $form->setInputFilter(new ViewFilter($option));
            $form->setData($data);
            if ($form->isValid()) {
                $values = $form->getData();

                // Save record
                $saveRecord              = Pi::model('record', 'forms')->createRow();
                $saveRecord->uid         = $uid;
                $saveRecord->form        = $singleForm['id'];
                $saveRecord->time_create = time();
                $saveRecord->ip          = Pi::user()->getIp();
                $saveRecord->save();

                // Set info
                $userName   = [];
                $userEmail  = '';
                $userMobile = '';

                // Save data
                foreach ($elements as $element) {
                    $elementKey = sprintf('element-%s', $element['id']);
                    if (isset($values[$elementKey]) && !empty($values[$elementKey])) {
                        if (is_array($values[$elementKey])) {
                            $values[$elementKey] = json_encode($values[$elementKey]);
                        }
                        $saveData              = Pi::model('data', 'forms')->createRow();
                        $saveData->record      = $saveRecord->id;
                        $saveData->uid         = Pi::user()->getId();
                        $saveData->form        = $singleForm['id'];
                        $saveData->time_create = time();
                        $saveData->element     = $element['id'];
                        $saveData->value       = _escape($values[$elementKey]);
                        $saveData->save();

                        if ($element['is_name']) {
                            $userName[] = $saveData->value;
                        }

                        if ($element['is_email']) {
                            $userEmail = $saveData->value;
                        }

                        if ($element['is_mobile']) {
                            $userMobile = $saveData->value;
                        }
                    }
                }

                // Update count
                Pi::model('form', 'forms')->increment('count', ['id' => $singleForm['id']]);

                // Set email
                Pi::api('notification', 'forms')->put(
                    [
                        'form_name'         => $singleForm['title'],
                        'user_name'         => !empty($userName) ? implode(' ', $userName) : '',
                        'user_email'        => $userEmail,
                        'user_mobile'       => $userMobile,
                        'notification_desc' => $config['notification_desc'],
                    ]
                );

                // Jump
                $this->jump(['action' => 'index'], __('Form input values saved successfully.'), 'success');
            }
        } else {
            $data = [
                'id' => $singleForm['id'],
            ];
            $form->setData($data);
        }

        // Get main image
        $mainImage = [];
        if (Pi::service('module')->isActive('media') && isset($singleForm['main_image']) && $singleForm['main_image'] > 0) {
            $mainImage = Pi::api('doc', 'media')->getSingleLinkData(
                $singleForm['main_image'],
                $config['main_image_height'],
                $config['main_image_width']
            );
        }

        // Set template
        $this->view()->setTemplate('form-view');
        $this->view()->assign('config', $config);
        $this->view()->assign('singleForm', $singleForm);
        $this->view()->assign('elements', $elements);
        $this->view()->assign('form', $form);
        $this->view()->assign('mainImage', $mainImage);
    }
}
