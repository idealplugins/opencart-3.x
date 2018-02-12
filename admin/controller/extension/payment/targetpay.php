<?php

/**
 *
 *    iDEALplugins.nl
 *  TargetPay plugin for Opencart 2.x, 3.x
 * Changelog: 20171120: apply for both 2.x 3.x
 *
 *  (C) Copyright Yellow Melon 2014
 *
 * @file        TargetPay Admin Controller
 *
 */

define('OC_VERSION', substr(VERSION, 0, 1));

class TargetPayAdmin extends Controller
{
    public function index()
    {
        //Check Opencart version.
        $redirectLink = (OC_VERSION == 2) ? 'extension' : 'marketplace';
        $token = (OC_VERSION == 2) ? 'token' : 'user_token';
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';
        

        $this->load->language('extension/payment/' . $this->type);
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('setting/setting');
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = '" . $this->db->escape($setting_name . $this->type) . "'");

            $this->model_setting_setting->editSetting($setting_name . $this->type, $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link($redirectLink . '/extension', $token . '=' . $this->session->data[$token] . '&type=payment', 'SSL'));
        }
        
        $data['type'] = $this->type;   //20171120

        $data['heading_title'] = $this->language->get('heading_title');
        
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_all_zones'] = $this->language->get('text_all_zones');
        $data['text_yes'] = $this->language->get('text_yes');
        $data['text_no'] = $this->language->get('text_no');
        
        $data['entry_rtlo'] = $this->language->get('entry_rtlo');
        $data['entry_test'] = $this->language->get('entry_test');
        $data['entry_transaction'] = $this->language->get('entry_transaction');
        $data['entry_total'] = $this->language->get('entry_total');
        $data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        
        $data['entry_canceled_status'] = $this->language->get('entry_canceled_status');
        $data['entry_pending_status'] = $this->language->get('entry_pending_status');
        
        $data['help_test'] = $this->language->get('help_test');
        $data['help_debug'] = $this->language->get('help_debug');
        $data['help_total'] = $this->language->get('help_total');
        
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        
        $data['tab_general'] = $this->language->get('tab_general');
        $data['tab_status'] = $this->language->get('tab_status');
        
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        
        if (isset($this->error['rtlo'])) {
            $data['error_rtlo'] = $this->error['rtlo'];
        } else {
            $data['error_rtlo'] = '';
        }
        
        $data['breadcrumbs'] = array();
        
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'),'href' => $this->url->link('common/dashboard', $token . '=' . $this->session->data[$token], 'SSL'));
        
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_payment'),'href' => $this->url->link($redirectLink . '/extension', $token . '=' . $this->session->data[$token] . '&type=payment', 'SSL'));
        
        $data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'),'href' => $this->url->link('extension/payment/' . $this->type, $token . '=' . $this->session->data[$token], 'SSL'));
        
        $data['action'] = $this->url->link('extension/payment/' . $this->type, $token . '=' . $this->session->data[$token], 'SSL');
        
        $data['cancel'] = $this->url->link($redirectLink . '/extension', $token . '=' . $this->session->data[$token] . '&type=payment', 'SSL');
        
        if (isset($this->request->post[$setting_name . $this->type . '_rtlo'])) {
            $data['payment_rtlo'] = $this->request->post[$setting_name . $this->type . '_rtlo'];
        } else {
            $data['payment_rtlo'] = $this->config->get($setting_name . $this->type . '_rtlo');
        }
        
        if (! isset($data['payment_rtlo'])) {
            $data['payment_rtlo'] = TargetPayCore::DEFAULT_RTLO; // Default TargetPay
        }
        
        if (isset($this->request->post[$setting_name . $this->type . '_test'])) {
            $data['payment_test'] = $this->request->post[$setting_name . $this->type . '_test'];
        } else {
            $data['payment_test'] = $this->config->get($setting_name . $this->type . '_test');
        }
        
        if (isset($this->request->post[$setting_name . $this->type . '_total'])) {
            $data['payment_total'] = $this->request->post[$setting_name . $this->type . '_total'];
        } else {
            $data['payment_total'] = $this->config->get($setting_name . $this->type . '_total');
        }
        
        if (! isset($data['payment_total'])) {
            $data['payment_total'] = 2;
        }
        
        if (isset($this->request->post[$setting_name . $this->type . '_pending_status_id'])) {
            $data['payment_pending_status_id'] = $this->request->post[$setting_name . $this->type . '_pending_status_id'];
        } else {
            $data['payment_pending_status_id'] = $this->config->get($setting_name . $this->type . '_pending_status_id');
        }
        
        // Bug fix for 2.0.0.0 ... everything defaults to canceled, not user friendly
        
        if (is_null($data['payment_pending_status_id'])) {
            $data['payment_pending_status_id'] = 1;
        }
        
        $this->load->model('localisation/order_status');
        
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        
        if (isset($this->request->post[$setting_name . $this->type . '_geo_zone_id'])) {
            $data['payment_geo_zone_id'] = $this->request->post[$setting_name . $this->type . '_geo_zone_id'];
        } else {
            $data['payment_geo_zone_id'] = $this->config->get($setting_name . $this->type . '_geo_zone_id');
        }
        
        $this->load->model('localisation/geo_zone');
        
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        
        if (isset($this->request->post[$setting_name . $this->type . '_status'])) {
            $data['payment_status'] = $this->request->post[$setting_name . $this->type . '_status'];
        } else {
            $data['payment_status'] = $this->config->get($setting_name . $this->type . '_status');
        }
        
        if (isset($this->request->post[$setting_name . $this->type . '_sort_order'])) {
            $data['payment_sort_order'] = $this->request->post[$setting_name . $this->type . '_sort_order'];
        } else {
            $data['payment_sort_order'] = $this->config->get($setting_name . $this->type . '_sort_order');
        }
        
        if (! isset($data['payment_sort_order'])) {
            $data['payment_sort_order'] = 1;
        }
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        //render admin general template, use for both 2.x, 3.x
        // 2.x use tpl, 3.x use twig
        $this->response->setOutput($this->load->view('extension/payment/targetpay', $data));
    }

    private function validate()
    {
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';

        if (! $this->user->hasPermission('modify', 'extension/payment/' . $this->type)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        if (! $this->request->post[$setting_name . $this->type . '_rtlo'] || $this->request->post[$setting_name . $this->type . '_rtlo'] == TargetPayCore::DEFAULT_RTLO) {
            $this->error['rtlo'] = $this->language->get('error_rtlo');
        }
        
        if (! $this->error) {
            return true;
        } else {
            return false;
        }
    }

    public function install()
    {
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';

        $this->load->model('extension/payment/' . $this->type);

        $this->{'model_extension_payment_' . $this->type}->createTable();

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting($setting_name . TargetPayCore::METHOD_AFTERPAY, array($this->type . '_status' => 1));
    }
}