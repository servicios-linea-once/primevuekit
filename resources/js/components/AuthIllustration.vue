<script setup>
/**
 * Ilustración del panel de marca: escudo, cubos y esferas.
 *
 * Es SVG inline a propósito: escala sin pixelarse, no añade una petición ni un binario al
 * repositorio, y los degradados usan los tokens del tema, así que sigue a la paleta.
 */
</script>

<template>
    <svg
        class="pvk-illustration w-full max-w-xs"
        viewBox="0 0 240 240"
        fill="none"
        role="img"
        aria-label="Ilustración decorativa de un escudo rodeado de formas geométricas"
    >
        <defs>
            <linearGradient id="pvk-shield" x1="70" y1="40" x2="180" y2="200" gradientUnits="userSpaceOnUse">
                <stop stop-color="#ffffff" stop-opacity="0.95" />
                <stop offset="1" stop-color="#c4b5fd" stop-opacity="0.75" />
            </linearGradient>
            <linearGradient id="pvk-cube-a" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#e9d5ff" />
                <stop offset="1" stop-color="#a78bfa" />
            </linearGradient>
            <linearGradient id="pvk-cube-b" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#a78bfa" />
                <stop offset="1" stop-color="#6b2bda" />
            </linearGradient>
            <radialGradient id="pvk-sphere-a" cx="0.35" cy="0.3" r="0.75">
                <stop stop-color="#fbcfe8" />
                <stop offset="1" stop-color="#db2777" />
            </radialGradient>
            <radialGradient id="pvk-sphere-b" cx="0.35" cy="0.3" r="0.75">
                <stop stop-color="#a5f3fc" />
                <stop offset="1" stop-color="#0ea5e9" />
            </radialGradient>
            <radialGradient id="pvk-glow" cx="0.5" cy="0.5" r="0.5">
                <stop stop-color="#ffffff" stop-opacity="0.35" />
                <stop offset="1" stop-color="#ffffff" stop-opacity="0" />
            </radialGradient>
        </defs>

        <circle cx="120" cy="120" r="110" fill="url(#pvk-glow)" />

        <!-- Escudo -->
        <g class="pvk-float-slow">
            <path
                d="M120 34 190 62v66c0 42-30 68-70 78-40-10-70-36-70-78V62z"
                fill="url(#pvk-shield)"
                fill-opacity="0.28"
                stroke="#ffffff"
                stroke-opacity="0.55"
                stroke-width="3"
            />
            <path
                d="m96 122 18 20 34-42"
                stroke="#ffffff"
                stroke-width="8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </g>

        <!-- Cubos -->
        <g class="pvk-float">
            <path d="m54 74 18-10 18 10-18 10z" fill="url(#pvk-cube-a)" />
            <path d="m54 74 18 10v20l-18-10z" fill="url(#pvk-cube-b)" fill-opacity="0.85" />
            <path d="m90 74-18 10v20l18-10z" fill="url(#pvk-cube-b)" fill-opacity="0.6" />
        </g>

        <g class="pvk-float-delay">
            <path d="m158 148 14-8 14 8-14 8z" fill="url(#pvk-cube-a)" />
            <path d="m158 148 14 8v16l-14-8z" fill="url(#pvk-cube-b)" fill-opacity="0.85" />
            <path d="m186 148-14 8v16l14-8z" fill="url(#pvk-cube-b)" fill-opacity="0.6" />
        </g>

        <g class="pvk-float-slow">
            <path d="m104 190 12-7 12 7-12 7z" fill="url(#pvk-cube-a)" fill-opacity="0.9" />
            <path d="m104 190 12 7v14l-12-7z" fill="url(#pvk-cube-b)" fill-opacity="0.8" />
            <path d="m128 190-12 7v14l12-7z" fill="url(#pvk-cube-b)" fill-opacity="0.55" />
        </g>

        <!-- Esferas -->
        <circle class="pvk-float-delay" cx="48" cy="150" r="16" fill="url(#pvk-sphere-a)" />
        <circle class="pvk-float" cx="196" cy="86" r="12" fill="url(#pvk-sphere-b)" />
        <circle class="pvk-float-slow" cx="176" cy="196" r="9" fill="url(#pvk-sphere-a)" fill-opacity="0.8" />
        <circle class="pvk-float" cx="66" cy="206" r="7" fill="url(#pvk-sphere-b)" fill-opacity="0.75" />
    </svg>
</template>

<style scoped>
@keyframes pvk-bob {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

.pvk-float,
.pvk-float-delay,
.pvk-float-slow {
    animation: pvk-bob 6s ease-in-out infinite;
    transform-origin: center;
}

.pvk-float-delay {
    animation-delay: -2s;
}

.pvk-float-slow {
    animation-duration: 9s;
    animation-delay: -4s;
}

@media (prefers-reduced-motion: reduce) {
    .pvk-float,
    .pvk-float-delay,
    .pvk-float-slow {
        animation: none;
    }
}
</style>
