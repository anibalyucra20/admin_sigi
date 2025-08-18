<?php
return [
  'library' => [
    'covers_base_url' => getenv('COVERS_BASE_URL') ?: BASE_URL.'/covers',
    'files_base_url'  => getenv('FILES_BASE_URL')  ?: BASE_URL.'/books',
  ],
];
