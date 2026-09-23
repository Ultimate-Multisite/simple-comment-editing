<?php

const SCE_SLUG = '';
const COOKIEPATH = '';

/**
 * Akismet functions loaded only when the optional plugin is active.
 *
 * @param mixed $key API key.
 *
 * @return string
 */
function akismet_verify_key( $key ) {}

/**
 * Check a stored comment with Akismet.
 *
 * @param int $comment_id Comment ID.
 *
 * @return string
 */
function akismet_check_db_comment( $comment_id ) {}
