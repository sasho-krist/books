<?php

// Bulgarian validation messages. Rules not listed here fall back to the English text.
return [
    'accepted' => 'Полето :attribute трябва да бъде прието.',
    'array' => 'Полето :attribute трябва да бъде масив.',
    'boolean' => 'Полето :attribute трябва да бъде вярно или грешно.',
    'confirmed' => 'Потвърждението на :attribute не съвпада.',
    'current_password' => 'Паролата е грешна.',
    'date' => 'Полето :attribute не е валидна дата.',
    'different' => 'Полетата :attribute и :other трябва да са различни.',
    'email' => 'Полето :attribute трябва да бъде валиден имейл адрес.',
    'enum' => 'Избраната стойност за :attribute е невалидна.',
    'exists' => 'Избраната стойност за :attribute е невалидна.',
    'filled' => 'Полето :attribute трябва да има стойност.',
    'in' => 'Избраната стойност за :attribute е невалидна.',
    'integer' => 'Полето :attribute трябва да бъде цяло число.',
    'lowercase' => 'Полето :attribute трябва да е с малки букви.',
    'numeric' => 'Полето :attribute трябва да бъде число.',
    'regex' => 'Форматът на :attribute е невалиден.',
    'required' => 'Полето :attribute е задължително.',
    'same' => 'Полетата :attribute и :other трябва да съвпадат.',
    'string' => 'Полето :attribute трябва да бъде текст.',
    'unique' => 'Стойността на :attribute вече е заета.',
    'url' => 'Полето :attribute трябва да бъде валиден адрес.',

    'between' => [
        'numeric' => 'Полето :attribute трябва да бъде между :min и :max.',
        'string' => 'Полето :attribute трябва да е между :min и :max символа.',
        'array' => 'Полето :attribute трябва да има между :min и :max елемента.',
        'file' => 'Файлът :attribute трябва да е между :min и :max килобайта.',
    ],
    'max' => [
        'numeric' => 'Полето :attribute не може да е по-голямо от :max.',
        'string' => 'Полето :attribute не може да е по-дълго от :max символа.',
        'array' => 'Полето :attribute не може да има повече от :max елемента.',
        'file' => 'Файлът :attribute не може да е по-голям от :max килобайта.',
    ],
    'min' => [
        'numeric' => 'Полето :attribute трябва да бъде поне :min.',
        'string' => 'Полето :attribute трябва да е поне :min символа.',
        'array' => 'Полето :attribute трябва да има поне :min елемента.',
        'file' => 'Файлът :attribute трябва да е поне :min килобайта.',
    ],
    'size' => [
        'numeric' => 'Полето :attribute трябва да бъде :size.',
        'string' => 'Полето :attribute трябва да е :size символа.',
        'array' => 'Полето :attribute трябва да има :size елемента.',
        'file' => 'Файлът :attribute трябва да е :size килобайта.',
    ],

    'password' => [
        'letters' => 'Паролата трябва да съдържа поне една буква.',
        'mixed' => 'Паролата трябва да съдържа поне една главна и една малка буква.',
        'numbers' => 'Паролата трябва да съдържа поне една цифра.',
        'symbols' => 'Паролата трябва да съдържа поне един символ.',
        'uncompromised' => 'Тази парола е изтекла при пробив на данни. Избери друга.',
    ],

    'custom' => [],

    'attributes' => [
        'q' => 'търсене',
        'lang' => 'език',
        'status' => 'статус',
        'rating' => 'рейтинг',
        'notes' => 'бележки',
        'page' => 'страница',
        'google_id' => 'книга',
        'name' => 'име',
        'email' => 'имейл',
        'password' => 'парола',
        'password_confirmation' => 'потвърждение на паролата',
        'current_password' => 'текуща парола',
    ],
];
