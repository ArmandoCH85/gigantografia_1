{{--
    POS Login Header Component - Diseño Minimalista Profesional
    Basado en principios de Material Design 3 y Apple HIG
    Compatible con Filament 3.x
--}}

<div class="pos-header">
    {{-- Logo minimalista --}}
    <img
        src="{{ asset('images/logoWayna.svg') }}"
        alt="Logo Wayna POS"
        class="pos-logo"
        loading="eager"
    >

    {{-- Título principal estilo DaisyUI --}}
    <h1 class="pos-title">
        Sistema de Gestión
    </h1>

    {{-- Badge de seguridad minimalista --}}
    <div class="pos-security-badge">
        <svg class="pos-security-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
        </svg>
        <span>Conexión Segura</span>
    </div>

    {{-- CSS Fix para Login Dark Mode --}}
    <link rel="stylesheet" href="{{ asset('css/login-daisyui-compiled.css') }}">
</div>

{{--
    Los estilos están definidos en pos-login-minimal.css
    Diseño minimalista sin animaciones innecesarias
--}}
