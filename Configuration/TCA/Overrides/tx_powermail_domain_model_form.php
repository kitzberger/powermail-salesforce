<?php

defined('TYPO3') || die('Access denied.');

call_user_func(function ($_EXTKEY = 'lin_salesforce', string $table = 'tx_powermail_domain_model_form'): void {
    $LLL = 'LLL:EXT:powermail_salesforce/Resources/Private/Language/locallang_db.xlf';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns($table, [
        'sf_enable' => [
            'exclude' => true,
            'label' => $LLL . ':tca.' . $table . '.sf_enable',
            'description' => $LLL . ':tca.' . $table . '.sf_enable.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => '0',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
            'onChange' => 'reload',
        ],
        'sf_mode' => [
            'exclude' => true,
            'label' => $LLL . ':tca.' . $table . '.sf_mode',
            'description' => $LLL . ':tca.' . $table . '.sf_mode.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $LLL . ':tca.' . $table . '.sf_mode.web2lead', 'value' => 'web2lead'],
                    ['label' => $LLL . ':tca.' . $table . '.sf_mode.web2case', 'value' => 'web2case'],
                ],
                'default' => 'web2lead',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
            'displayCond' => 'FIELD:sf_enable:REQ:true',
            'onChange' => 'reload',
        ],
        'sf_oid' => [
            'exclude' => true,
            'label' => $LLL . ':tca.' . $table . '.sf_oid',
            'description' => $LLL . ':tca.' . $table . '.sf_oid.description',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'required' => true,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
            'displayCond' => [
                'AND' => [
                    'FIELD:sf_enable:REQ:true',
                    'FIELD:sf_mode:=:web2lead',
                ],
            ],
        ],
        'sf_record_type_id' => [
            'exclude' => true,
            'label' => $LLL . ':tca.' . $table . '.sf_record_type_id',
            'description' => $LLL . ':tca.' . $table . '.sf_record_type_id.description',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'max' => 100,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
            'displayCond' => [
                'AND' => [
                    'FIELD:sf_enable:REQ:true',
                    'FIELD:sf_mode:=:web2case',
                ],
            ],
        ],
    ]);

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
        $table,
        'tx_linsalesforce_fields',
        'sf_enable,sf_mode,sf_oid,sf_record_type_id'
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
        $table,
        '--palette--;' . $LLL . ':sf_paletteLabel;tx_linsalesforce_fields',
        '',
        'after:pages'
    );
});
