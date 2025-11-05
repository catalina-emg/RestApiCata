async function getData(recurso) {
    try {
        const res = await fetch(`/rest-api-catalina/api/${recurso}`);
        if (!res.ok) {
            const text = await res.text();
            document.getElementById('output').textContent = `Error HTTP ${res.status}: ${text}`;
            document.getElementById('resultado').innerHTML = 'Error al obtener datos.';
            return;
        }

        const data = await res.json();
        document.getElementById('output').textContent = JSON.stringify(data, null, 2);

        const contenedor = document.getElementById('resultado');
        const datos = data;

        if (Array.isArray(datos)) {
            contenedor.innerHTML = datos
                .map(item => Object.values(item).join(" - "))
                .join("<br>");
        } else {
            contenedor.innerHTML = 'El recurso no es una lista o no hay datos.';
        }
    } catch (err) {
        document.getElementById('output').textContent = `Fetch error: ${err.message}`;
        document.getElementById('resultado').innerHTML = 'Error de conexión.';
    }
}

async function createUser(){
    const nuevo = { id: 25, nombre: "catalina García", rol: "Desarrolladora"}; 
    // POST al recurso correcto (tabla 'usuarios' en el API)
    const res = await fetch(`/rest-api-catalina/api/usuarios`, {
        method: 'POST',
        body: JSON.stringify(nuevo),
        headers: {
            'Content-Type': "application/json"
        }
    });
    if (!res.ok) {
        const t = await res.text();
        document.getElementById('output').textContent = `Error HTTP ${res.status}: ${t}`;
        document.getElementById('resultado').innerHTML = 'Error al crear usuario.';
        return;
    }
    const data = await res.json();
    document.getElementById('output').textContent = JSON.stringify(data, null, 2);
    document.getElementById('resultado').innerHTML = 'Usuario Creado';
    
    // Registrar el evento de creación en el log
    try {
        await fetch('/rest-api-catalina/api/logevent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                nombre: nuevo.nombre,
                accion: 'crear_usuario'
            })
        });
    } catch (error) {
        console.error('Error al registrar el evento:', error);
    }
}

async function updateUser(){
    const actualizado = { id: 2, nombre: "Carlos Martínez", rol: "Analista"}; 
    const res = await fetch(`/rest-api-catalina/api/usuarios`, {
        method: 'PATCH',
        body: JSON.stringify(actualizado),
        headers: {
            'Content-Type': "application/json"
        }
    });
    if (!res.ok) {
        const t = await res.text();
        document.getElementById('output').textContent = `Error HTTP ${res.status}: ${t}`;
        document.getElementById('resultado').innerHTML = 'Error al actualizar.';
        return;
    }
    const data = await res.json();
    document.getElementById('output').textContent = JSON.stringify(data, null, 2);
    document.getElementById('resultado').innerHTML = 'Usuario Actualizado';
}

async function deleteUser(){ 
    const res = await fetch(`/rest-api-catalina/api/usuarios`, {
        method: 'DELETE',
        body: JSON.stringify({ id: 3 }),
        headers: {
            'Content-Type': "application/json"
        }
    });
    if (!res.ok) {
        const t = await res.text();
        document.getElementById('output').textContent = `Error HTTP ${res.status}: ${t}`;
        document.getElementById('resultado').innerHTML = 'Error al borrar.';
        return;
    }
    const data = await res.json();
    document.getElementById('output').textContent = JSON.stringify(data, null, 2);
    document.getElementById('resultado').innerHTML = 'Usuario Borrado';
}

// Enviar evento al endpoint /api/logevent (no inserta en BD, sólo registra)
async function sendLogEvent(){
    const nombre = (document.getElementById('le_nombre').value || '').trim();
    const accion = (document.getElementById('le_accion').value || '').trim();
    const errEl = document.getElementById('logEventError');

    // Validación cliente: sólo letras y espacios (Unicode)
    const nameRegex = /^[\p{L}\s]+$/u;
    if (!nameRegex.test(nombre)){
        errEl.textContent = 'Nombre inválido: use sólo letras y espacios.';
        return;
    }
    errEl.textContent = '';

    const payload = { nombre };
    if (accion) payload.accion = accion;

    try{
        const res = await fetch(`/rest-api-catalina/api/logevent`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok){
            const t = await res.text();
            document.getElementById('output').textContent = `Error HTTP ${res.status}: ${t}`;
            document.getElementById('resultado').innerHTML = 'Error al enviar evento.';
            return;
        }
        const data = await res.json();
        document.getElementById('output').textContent = JSON.stringify(data, null, 2);
        document.getElementById('resultado').innerHTML = 'Evento registrado.';
        // limpiar
        document.getElementById('le_nombre').value = '';
        document.getElementById('le_accion').value = '';
    }catch(err){
        document.getElementById('output').textContent = `Fetch error: ${err.message}`;
        document.getElementById('resultado').innerHTML = 'Error de conexión.';
    }
}

// Crear usuario desde el formulario (usado por index.html)
async function createUserFromForm(){
    const nombreEl = document.getElementById('cu_nombre');
    const edadEl = document.getElementById('cu_edad');
    const rolEl = document.getElementById('cu_rol');
    const errEl = document.getElementById('cu_error');

    const nombre = (nombreEl.value || '').trim();
    const edad = (edadEl.value || '').trim();
    const rol = (rolEl.value || '').trim();

    // Validación cliente: sólo letras y espacios (Unicode)
    const nameRegex = /^[\p{L}\s]+$/u;
    if (!nameRegex.test(nombre)){
        errEl.textContent = 'Nombre inválido: use sólo letras y espacios.';
        nombreEl.focus();
        return;
    }
    if (!rol){
        errEl.textContent = 'El rol es requerido.';
        rolEl.focus();
        return;
    }
    if (!edad || isNaN(Number(edad)) || Number(edad) < 0) {
        errEl.textContent = 'Edad inválida.';
        edadEl.focus();
        return;
    }
    errEl.textContent = '';

    const nuevo = { nombre, edad: Number(edad), rol };

    try {
        console.log('=== INICIANDO CREACIÓN DE USUARIO ===');
        console.log('Enviando datos:', nuevo);
        
        const res = await fetch(`/rest-api-catalina/api/usuarios`, {
            method: 'POST',
            body: JSON.stringify(nuevo),
            headers: { 'Content-Type': 'application/json' }
        });

        console.log('✅ Fetch completado');
        console.log('Status:', res.status);
        console.log('Status Text:', res.statusText);
        console.log('OK:', res.ok);
        
        // SOLUCIÓN: Clonar la respuesta antes de leerla
        const responseClone = res.clone();
        
        let responseData;
        try {
            console.log('Intentando leer como JSON...');
            responseData = await res.json();
            console.log('✅ Lectura JSON exitosa:', responseData);
        } catch (e) {
            console.log('❌ Falló JSON, intentando como texto de la respuesta clonada...');
            responseData = await responseClone.text();
            console.log('✅ Lectura texto exitosa:', responseData);
        }

        console.log('Respuesta final del servidor:', responseData);

        if (!res.ok){
            document.getElementById('output').textContent = JSON.stringify({
                status: res.status,
                error: responseData
            }, null, 2);
            document.getElementById('resultado').innerHTML = 'Error al crear usuario. Revisa la consola.';
            return;
        }

        // Si llegamos aquí, status es 200
        document.getElementById('output').textContent = typeof responseData === 'string' ? responseData : JSON.stringify(responseData, null, 2);
        document.getElementById('resultado').innerHTML = '✅ Usuario Creado Exitosamente';

        // Enviar evento de log (opcional)
        try{
            await fetch(`/rest-api-catalina/api/logevent`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre, accion: 'crear_usuario' })
            });
        }catch(err){
            console.warn('No se pudo registrar logevent:', err);
        }

        // limpiar formulario
        document.getElementById('createUserForm').reset();
        
        console.log('=== FIN DE CREACIÓN ===');
    } catch (err){
        console.error('Error completo:', err);
        document.getElementById('output').textContent = `Fetch error: ${err.message}`;
        document.getElementById('resultado').innerHTML = 'Error de conexión.';
    }
}

function clearMessages(){
    const err = document.getElementById('cu_error');
    if (err) err.textContent = '';
    const out = document.getElementById('output');
    if (out) out.textContent = 'Esperando solicitud...';
    const res = document.getElementById('resultado');
    if (res) res.innerHTML = '';
}

// Actualizar usuario desde el formulario
async function updateUserFromForm(){
    const idEl = document.getElementById('uu_id');
    const nombreEl = document.getElementById('uu_nombre');
    const edadEl = document.getElementById('uu_edad');
    const rolEl = document.getElementById('uu_rol');
    const errEl = document.getElementById('uu_error');

    const id = (idEl.value || '').trim();
    const nombre = (nombreEl.value || '').trim();
    const edad = (edadEl.value || '').trim();
    const rol = (rolEl.value || '').trim();

    if (!id || isNaN(Number(id))) {
        errEl.textContent = 'ID inválido.';
        idEl.focus();
        return;
    }

    // Si se proporciona nombre, validar
    const nameRegex = /^[\p{L}\s]+$/u;
    if (nombre && !nameRegex.test(nombre)){
        errEl.textContent = 'Nombre inválido: use sólo letras y espacios.';
        nombreEl.focus();
        return;
    }
    if (edad && (isNaN(Number(edad)) || Number(edad) < 0)) {
        errEl.textContent = 'Edad inválida.';
        edadEl.focus();
        return;
    }

    errEl.textContent = '';

    const payload = { id: Number(id) };
    if (nombre) payload.nombre = nombre;
    if (edad) payload.edad = edad;
    if (rol) payload.rol = rol;

    try{
        const res = await fetch(`/rest-api-catalina/api/usuarios`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        let responseData;
        try {
            responseData = await res.json();
        } catch (e) {
            responseData = await res.text();
        }

        console.log('Respuesta del servidor:', responseData);

        if (!res.ok){
            document.getElementById('output').textContent = typeof responseData === 'string' ? responseData : JSON.stringify(responseData, null, 2);
            document.getElementById('resultado').innerHTML = responseData.message || responseData.error || 'Error al actualizar usuario.';
            return;
        }

        document.getElementById('output').textContent = typeof responseData === 'string' ? responseData : JSON.stringify(responseData, null, 2);
        document.getElementById('resultado').innerHTML = 'Usuario Actualizado';
        document.getElementById('updateUserForm').reset();
    } catch(err){
        document.getElementById('output').textContent = `Fetch error: ${err.message}`;
        document.getElementById('resultado').innerHTML = 'Error de conexión.';
    }
}

// Borrar usuario desde el formulario
async function deleteUserFromForm(){
    const idEl = document.getElementById('du_id');
    const errEl = document.getElementById('du_error');

    const id = (idEl.value || '').trim();
    if (!id || isNaN(Number(id))) {
        errEl.textContent = 'ID inválido.';
        idEl.focus();
        return;
    }
    errEl.textContent = '';

    try{
        const res = await fetch(`/rest-api-catalina/api/usuarios`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: Number(id) })
        });

        let responseData;
        try {
            responseData = await res.json();
        } catch (e) {
            responseData = await res.text();
        }

        console.log('Respuesta del servidor:', responseData);

        if (!res.ok){
            document.getElementById('output').textContent = typeof responseData === 'string' ? responseData : JSON.stringify(responseData, null, 2);
            document.getElementById('resultado').innerHTML = responseData.message || responseData.error || 'Error al borrar usuario.';
            return;
        }

        document.getElementById('output').textContent = typeof responseData === 'string' ? responseData : JSON.stringify(responseData, null, 2);
        document.getElementById('resultado').innerHTML = 'Usuario Borrado';
        document.getElementById('deleteUserForm').reset();
    } catch(err){
        document.getElementById('output').textContent = `Fetch error: ${err.message}`;
        document.getElementById('resultado').innerHTML = 'Error de conexión.';
    }
}