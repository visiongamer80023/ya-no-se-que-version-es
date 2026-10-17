<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }

        form {
            width: 350px;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .2);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .input-container {
            position: relative;
            margin-bottom: 15px;
        }

        input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            box-sizing: border-box;
        }

        .input-container i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
        }

        .captcha-box {
            background: #f1f1f1;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .mensaje-error {
            display: none;
            margin: -5px 0 15px;
            color: #b00020;
            font-size: 14px;
        }

        button {
            width: 100%;
            padding: 12px;
            cursor: pointer;
            margin-top: 10px;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 10px;
        }

        .boton-login {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            box-sizing: border-box;
            text-decoration: none;
            background: #333;
            color: white;
            border: 1px solid #333;
            text-align: center;
            cursor: pointer;
        }
    </style>
</head>
<body>

<form id="registroForm" action="send.php" method="POST" autocomplete="off">
    <h2>Bienvenido</h2>

    <div class="input-container">
        <input
            type="text"
            id="usuario"
            name="usuario"
            placeholder="Usuario: exactamente 4 números"
            inputmode="numeric"
            maxlength="4"
            pattern="[0-9]{4}"
            title="El usuario debe contener exactamente 4 números"
            required
        >
        <i class="fa-solid fa-user"></i>
    </div>
    <p class="mensaje-error" id="errorUsuario">
        El usuario debe contener exactamente 4 números, sin letras ni símbolos.
    </p>

    <div class="input-container">
        <input type="password" name="password" placeholder="Contraseña" required>
        <i class="fa-solid fa-lock"></i>
    </div>

    <div class="captcha-box" id="captchaPregunta"></div>

    <div class="input-container">
        <input
            type="text"
            id="captchaRespuesta"
            placeholder="Resuelve el CAPTCHA"
            required
        >
        <i class="fa-solid fa-shield-halved"></i>
    </div>

    <a href="#">Términos y condiciones</a>

    <button type="submit" name="send" value="1">Registrarse</button>
    <a class="boton-login" href="login.php">Iniciar sesión</a>
</form>

<script>
let respuestaCorrecta = "";

function generarCaptcha() {
    const a = Math.floor(Math.random() * 20) + 1;
    const b = Math.floor(Math.random() * 10) + 1;

    const operaciones = [
        { texto: `${a} + ${b}`, resultado: (a + b).toString() },
        { texto: `${a} - ${b}`, resultado: (a - b).toString() },
        { texto: `${a} × ${b}`, resultado: (a * b).toString() }
    ];

    const op = operaciones[Math.floor(Math.random() * operaciones.length)];

    document.getElementById("captchaPregunta").innerText = "Resuelve: " + op.texto;
    respuestaCorrecta = op.resultado;
}

generarCaptcha();

const formulario = document.getElementById("registroForm");
const usuarioInput = document.getElementById("usuario");
const errorUsuario = document.getElementById("errorUsuario");
//tener un captcha en el login
usuarioInput.addEventListener("input", function () {
    const esValido = /^[0-9]{0,4}$/.test(this.value);
    errorUsuario.style.display = esValido ? "none" : "block";
});

formulario.addEventListener("submit", function (e) {
    e.preventDefault();

    const usuario = usuarioInput.value.trim();
    const respuesta = document.getElementById("captchaRespuesta").value.trim();

    if (!/^[0-9]{4}$/.test(usuario)) {
        errorUsuario.style.display = "block";
        usuarioInput.focus();
        return;
    }

    errorUsuario.style.display = "none";

    if (respuesta !== respuestaCorrecta) {
        alert("CAPTCHA incorrecto.");
        document.getElementById("captchaRespuesta").value = "";
        generarCaptcha();
        return;
    }

    // Envía realmente los datos a send.php después de validar.
    formulario.submit();
});
</script>

</body>
</html>