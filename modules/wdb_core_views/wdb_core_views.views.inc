<?php

/**
 * @file
 * Provides views data for wdb_core entities.
 *
 * This file defines the database tables, fields, and relationships
 * for the WDB Core module, making them available to the Views module.
 */

/**
 * Implements hook_views_data().
 */
function wdb_core_views_views_data() {
  $data = [];

  // ================================================================
  // Base Table Definitions (Selectable as the starting point in Views)
  // ================================================================
  // ----------------------------------------------------------------
  // Base Table: WDB Word Unit (wdb_word_unit_field_data)
  // The primary entity for building views about words in context.
  // ----------------------------------------------------------------
  $data['wdb_word_unit_field_data']['table']['group'] = t('WDB Content');
  $data['wdb_word_unit_field_data']['table']['provider'] = 'wdb_core';
  $data['wdb_word_unit_field_data']['table']['base'] = [
    'field' => 'id',
    'title' => t('WDB Word Unit'),
    'help' => t('WDB Word Unit entities, representing a word in a specific context.'),
    'weight' => -10,
  ];

  // Fields for WdbWordUnit.
  $data['wdb_word_unit_field_data']['id'] = [
    'title' => t('Word Unit ID'),
    'help' => t('The unique ID of the Word Unit.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
  ];
  $data['wdb_word_unit_field_data']['original_word_unit_identifier'] = [
    'title' => t('Original Word Unit Identifier'),
    'help' => t('The original identifier from the source data.'),
    'field' => ['id' => 'standard'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_word_unit_field_data']['realized_form'] = [
    'title' => t('Realized Form'),
    'help' => t('The actual form of the word as it appears in the text.'),
    'field' => ['id' => 'standard'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_word_unit_field_data']['word_sequence'] = [
    'title' => t('Word Sequence'),
    'help' => t('The sequence number of the word in its context.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
  ];
  $data['wdb_word_unit_field_data']['word_unit_note'] = [
    'title' => t('Word Unit Note'),
    'help' => t('A note associated with the Word Unit.'),
    'field' => [
      'id' => 'standard',
      'real field' => 'note__value',
    ],
    'filter' => [
      'id' => 'string',
      'real field' => 'note__value',
    ],
  ];

  // Relationships from WdbWordUnit.
  $data['wdb_word_unit_field_data']['word_meaning_ref'] = [
    'title' => t('Word Meaning'),
    'help' => t('Relates to the meaning of this word unit.'),
    'relationship' => [
      'base' => 'wdb_word_meaning_field_data',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Word Meaning'),
    ],
  ];
  $data['wdb_word_unit_field_data']['source_ref'] = [
    'title' => t('Source'),
    'help' => t('Relates to the source document.'),
    'relationship' => [
      'base' => 'wdb_source',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Source'),
    ],
  ];

  // Add relationships for all grammatical category taxonomy terms.
  $grammar_categories = [
    'person_ref' => t('Person'),
    'gender_ref' => t('Gender'),
    'number_ref' => t('Number'),
    'verbal_form_ref' => t('Verbal Form'),
    'aspect_ref' => t('Aspect'),
    'mood_ref' => t('Mood'),
    'voice_ref' => t('Voice'),
    'grammatical_case_ref' => t('Grammatical Case'),
  ];

  foreach ($grammar_categories as $field_name => $label) {
    $data['wdb_word_unit_field_data'][$field_name] = [
      'title' => $label,
      'help' => t('Relates the Word Unit to its @label taxonomy term.', ['@label' => strtolower($label->render())]),
      'relationship' => [
        'base' => 'taxonomy_term_field_data',
        'base field' => 'tid',
        'id' => 'standard',
        'label' => $label,
      ],
    ];
  }

  // ----------------------------------------------------------------
  // Base Table: WDB Source (wdb_source)
  // ----------------------------------------------------------------
  $data['wdb_source']['table']['group'] = t('WDB Content');
  $data['wdb_source']['table']['base'] = [
    'field' => 'id',
    'title' => t('WDB Source'),
    'help' => t('WDB Source entities (documents).'),
  ];
  $data['wdb_source']['id'] = [
    'title' => t('Source ID'),
    'help' => t('The unique ID of the Source.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
  ];
  $data['wdb_source']['displayname'] = [
    'title' => t('Source Title'),
    'help' => t('The display name of the Source.'),
    'field' => ['id' => 'standard'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_source']['source_identifier'] = [
    'title' => t('Source Identifier'),
    'help' => t('The unique string identifier of the Source.'),
    'field' => ['id' => 'standard'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_source']['pages'] = [
    'title' => t('Number of Pages'),
    'help' => t('The total number of pages in the Source document.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
  ];
  $data['wdb_source']['title_statement'] = [
    'title' => t('Title Statement'),
    'help' => t('The title statement of the Source, used in manifests.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_source']['description__value'] = [
    'title' => t('Source Description'),
    'help' => t('The description of the Source document.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];

  // Relationship from WdbSource to its Subsystem taxonomy term.
  // Because 'subsystem_tags' is a multi-value field, we must expose the
  // intermediate field table and define the relationship from there.
  $data['wdb_source__subsystem_tags']['table']['group'] = t('WDB Content');
  $data['wdb_source__subsystem_tags']['table']['join'] = [
    'wdb_source' => [
      'left_field' => 'id',
      'field' => 'entity_id',
    ],
  ];
  $data['wdb_source__subsystem_tags']['subsystem_tags_target_id'] = [
    'title' => t('Subsystem'),
    'help' => t('Relates the source to its subsystem taxonomy term.'),
    'relationship' => [
      'base' => 'taxonomy_term_field_data',
      'base field' => 'tid',
      'id' => 'standard',
      'label' => t('Subsystem'),
    ],
  ];

  // ----------------------------------------------------------------
  // Base Table: WDB Word (Lemma) (wdb_word_field_data)
  // ----------------------------------------------------------------
  $data['wdb_word_field_data']['table']['group'] = t('WDB Content');
  $data['wdb_word_field_data']['table']['base'] = [
    'field' => 'id',
    'title' => t('WDB Word (Lemma)'),
    'help' => t('WDB Word entities (lemmas).'),
  ];
  $data['wdb_word_field_data']['id'] = [
    'title' => t('Word ID'),
    'help' => t('The unique ID of the Word.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
  ];
  $data['wdb_word_field_data']['word_code'] = [
    'title' => t('Word Code'),
    'help' => t('The unique code for the Word (basic_form + category).'),
    'field' => ['id' => 'standard'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_word_field_data']['basic_form'] = [
    'title' => t('Basic Form (Lemma)'),
    'help' => t('The dictionary form of the Word.'),
    'field' => ['id' => 'standard'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_word_field_data']['lexical_category_ref'] = [
    'title' => t('Lexical Category'),
    'help' => t('Relates the Word to its lexical category taxonomy term.'),
    'relationship' => [
      'base' => 'taxonomy_term_field_data',
      'base field' => 'tid',
      'id' => 'standard',
      'label' => t('Lexical Category'),
    ],
  ];

  // ----------------------------------------------------------------
  // Base Table: WDB Sign (wdb_sign_field_data)
  // ----------------------------------------------------------------
  $data['wdb_sign_field_data']['table']['group'] = t('WDB Content');
  $data['wdb_sign_field_data']['table']['base'] = [
    'field' => 'id',
    'title' => t('WDB Sign'),
    'help' => t('WDB Sign entities.'),
  ];
  $data['wdb_sign_field_data']['id'] = [
    'title' => t('Sign ID'),
    'help' => t('The unique ID of the Sign.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
  ];
  $data['wdb_sign_field_data']['sign_code'] = [
    'title' => t('Sign Code'),
    'help' => t('The unique code representing the Sign.'),
    'field' => ['id' => 'standard'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];

  // ----------------------------------------------------------------
  // Base Table: WDB Annotation Page (wdb_annotation_page_field_data)
  // ----------------------------------------------------------------
  $data['wdb_annotation_page_field_data']['table']['group'] = t('WDB Content');
  $data['wdb_annotation_page_field_data']['table']['base'] = [
    'field' => 'id',
    'title' => t('WDB Annotation Page'),
    'help' => t('WDB Annotation Page entities.'),
  ];
  $data['wdb_annotation_page_field_data']['id'] = [
    'title' => t('Annotation Page ID'),
    'help' => t('The unique ID of the Annotation Page.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
  ];
  $data['wdb_annotation_page_field_data']['page_number'] = [
    'title' => t('Page Number'),
    'help' => t('The page number within the source document.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];
  $data['wdb_annotation_page_field_data']['page_name'] = [
    'title' => t('Page Label'),
    'help' => t('The human-readable label for the page.'),
    'field' => ['id' => 'standard'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_annotation_page_field_data']['annotation_code'] = [
    'title' => t('Annotation Code'),
    'help' => t('The unique code for the annotation page (source_id + page_num).'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_annotation_page_field_data']['image_identifier'] = [
    'title' => t('IIIF Image Identifier'),
    'help' => t('IIIF Image Identifier.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_annotation_page_field_data']['source_ref'] = [
    'title' => t('Source'),
    'help' => t('Relates the Annotation Page to its parent Source document.'),
    'relationship' => [
      'base' => 'wdb_source',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Source'),
    ],
  ];

  // ================================================================
  // Join-Only Table Definitions (Available only via relationships)
  // ================================================================
  // ----------------------------------------------------------------
  // Intermediate Table: wdb_word_unit__annotation_page_refs
  // ----------------------------------------------------------------
  $data['wdb_word_unit__annotation_page_refs']['table']['group'] = t('WDB Content');
  $data['wdb_word_unit__annotation_page_refs']['table']['join']['wdb_word_unit_field_data'] = [
    'left_field' => 'id',
    'field' => 'entity_id',
  ];
  $data['wdb_word_unit__annotation_page_refs']['annotation_page_refs_target_id'] = [
    'title' => t('Annotation Page'),
    'help' => t('The Annotation Page this Word Unit appears on.'),
    'relationship' => [
      'base' => 'wdb_annotation_page_field_data',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Annotation Page'),
    ],
  ];

  // ----------------------------------------------------------------
  // Table: WDB Word Meaning (wdb_word_meaning_field_data)
  // ----------------------------------------------------------------
  $data['wdb_word_meaning_field_data']['table']['group'] = t('WDB Content');
  $data['wdb_word_meaning_field_data']['table']['join']['wdb_word_unit_field_data'] = [
    'left_field' => 'word_meaning_ref',
    'field' => 'id',
  ];
  $data['wdb_word_meaning_field_data']['word_ref'] = [
    'title' => t('Word (Lemma)'),
    'help' => t('Relates the Word Meaning to its parent Word (lemma).'),
    'relationship' => [
      'base' => 'wdb_word_field_data',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Word (Lemma)'),
    ],
  ];
  $data['wdb_word_meaning_field_data']['word_meaning_code'] = [
    'title' => t('Word Meaning Code'),
    'help' => t('The unique code for the Word Meaning.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_word_meaning_field_data']['explanation__value'] = [
    'title' => t('Explanation'),
    'help' => t('The explanation of the Word Meaning.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];

  // ----------------------------------------------------------------
  // Table: WDB Word Map (wdb_word_map)
  // ----------------------------------------------------------------
  $data['wdb_word_map']['table']['group'] = t('WDB Content');
  $data['wdb_word_map']['table']['join']['wdb_word_unit_field_data'] = [
    'left_field' => 'id',
    'field' => 'word_unit_ref',
  ];
  $data['wdb_word_map']['sign_interpretation_ref'] = [
    'title' => t('Sign Interpretation'),
    'help' => t('The Interpretation of the Sign in the context of the Word Unit.'),
    'relationship' => [
      'base' => 'wdb_sign_interpretation_field_data',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Sign Interpretation'),
    ],
  ];
  $data['wdb_word_map']['sign_sequence'] = [
    'title' => t('Sign Sequence'),
    'help' => t('The sequence number of the Sign in the word unit.'),
    'field' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
  ];

  // ----------------------------------------------------------------
  // Table: WDB Sign Interpretation (wdb_sign_interpretation_field_data)
  // ----------------------------------------------------------------
  $data['wdb_sign_interpretation_field_data']['table']['group'] = t('WDB Content');
  $data['wdb_sign_interpretation_field_data']['table']['join']['wdb_word_map'] = [
    'left_field' => 'sign_interpretation_ref',
    'field' => 'id',
  ];
  $data['wdb_sign_interpretation_field_data']['sign_function_ref'] = [
    'title' => t('Sign Function'),
    'help' => t('Relates the interpretation to its Sign Function (e.g., phonetic, logogram).'),
    'relationship' => [
      'base' => 'wdb_sign_function_field_data',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Sign Function'),
    ],
  ];
  $data['wdb_sign_interpretation_field_data']['label_ref'] = [
    'title' => t('Label'),
    'help' => t('Relates the interpretation to the drawn polygon (Label) on the image.'),
    'relationship' => [
      'base' => 'wdb_label',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Label'),
    ],
  ];
  $data['wdb_sign_interpretation_field_data']['phone'] = [
    'title' => t('Phone'),
    'help' => t('The phonetic value or reading of the sign.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];
  $data['wdb_sign_interpretation_field_data']['line_number'] = [
    'title' => t('Line Number'),
    'help' => t('The line number on the page where the sign appears.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
  ];
  $data['wdb_sign_interpretation_field_data']['sign_interpretation_note'] = [
    'title' => t('Sign Interpretation Note'),
    'help' => t('A note associated with the Sign Interpretation.'),
    'field' => [
      'id' => 'standard',
      'real field' => 'note__value',
    ],
    'filter' => [
      'id' => 'string',
      'real field' => 'note__value',
    ],
  ];

  // ----------------------------------------------------------------
  // Table: WDB Label (wdb_label)
  // ----------------------------------------------------------------
  $data['wdb_label']['table']['group'] = t('WDB Content');
  $data['wdb_label']['table']['join']['wdb_sign_interpretation_field_data'] = [
    'left_field' => 'label_ref',
    'field' => 'id',
  ];
  $data['wdb_label']['label_name'] = [
    'title' => t('Label Name'),
    'help' => t('The textual name of the Label (e.g., "1-1").'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];

  // ----------------------------------------------------------------
  // Table: WDB Sign Function (wdb_sign_function_field_data)
  // ----------------------------------------------------------------
  $data['wdb_sign_function_field_data']['table']['group'] = t('WDB Content');
  $data['wdb_sign_function_field_data']['table']['join']['wdb_sign_interpretation_field_data'] = [
    'left_field' => 'sign_function_ref',
    'field' => 'id',
  ];
  $data['wdb_sign_function_field_data']['sign_ref'] = [
    'title' => t('Sign'),
    'help' => t('Relates the Sign Function to its parent Sign.'),
    'relationship' => [
      'base' => 'wdb_sign_field_data',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Sign'),
    ],
  ];
  $data['wdb_sign_function_field_data']['function_name'] = [
    'title' => t('Function Name'),
    'help' => t('The name of the function (e.g., "phonetic").'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
  ];

  return $data;
}
