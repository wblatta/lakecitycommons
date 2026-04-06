import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', 'serif'],
            },
            colors: {
                forest: {
                    DEFAULT: '#2D6A4F',
                    light: '#52B788',
                    pale: '#B7E4C7',
                    dark: '#1B4332',
                },
                earth: {
                    DEFAULT: '#1B2D24',
                    muted: '#6B7C72',
                },
                cream: '#F8F9F4',
                amber: '#D4A017',
            },
            borderRadius: {
                card: '1rem',
            },
        },
    },

    plugins: [forms],
};
