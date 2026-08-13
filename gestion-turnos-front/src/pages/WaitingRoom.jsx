import { useState, useEffect } from 'react';
import { echo } from '../services/echo';
import api from '../api/axios';

// Tono de llamada generado con Web Audio API: evita depender de un archivo de audio.
function playChime() {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;

    const ctx = new AudioCtx();
    const now = ctx.currentTime;

    [880, 1320].forEach((freq, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0.0001, now + i * 0.18);
      gain.gain.exponentialRampToValueAtTime(0.25, now + i * 0.18 + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.18 + 0.35);
      osc.connect(gain).connect(ctx.destination);
      osc.start(now + i * 0.18);
      osc.stop(now + i * 0.18 + 0.4);
    });

    setTimeout(() => ctx.close(), 1200);
  } catch (err) {
    console.warn('No se pudo reproducir el tono de llamada', err);
  }
}

/**
 * Voz fija de los anuncios. La pantalla es solo de visualización: no se elige
 * ni se configura desde la interfaz.
 *
 * Los respaldos son por si el navegador no la tiene (las voces de Google solo
 * existen en Chrome): antes que quedarse mudo, usa otra voz en español.
 */
const VOZ_ANUNCIOS = 'Google español';

function vozDeAnuncio() {
  const enEspanol = (window.speechSynthesis?.getVoices?.() ?? [])
    .filter((v) => /^es/i.test(v.lang));

  return enEspanol.find((v) => v.name === VOZ_ANUNCIOS)
    ?? enEspanol.find((v) => /google/i.test(v.name))
    ?? enEspanol[0]
    ?? null;
}

// El sintetizador lee "Dr." como sigla; conviene darle la palabra entera.
function paraLeer(nombre = '') {
  return nombre
    .replace(/\bDra\.\s*/gi, 'Doctora ')
    .replace(/\bDr\.\s*/gi, 'Doctor ')
    .replace(/\bLic\.\s*/gi, 'Licenciado ');
}

/**
 * Anuncia el llamado por voz: nombre, sala y —al final— el profesional.
 *
 * Se emite en dos frases separadas en vez de una sola: el sintetizador mete
 * una pausa natural entre utterances, y eso suena mucho menos monótono que
 * una única oración larga con comas.
 */
function anunciarPorVoz(llamado) {
  const sintetizador = window.speechSynthesis;
  if (!sintetizador) return;

  const voz = vozDeAnuncio();

  const frases = [];
  // Los signos de exclamación le dan entonación de llamado, no de lectura.
  frases.push(`¡${llamado.paciente}!`);

  if (llamado.sala) {
    const profesional = llamado.profesional
      ? `, con el profesional ${paraLeer(llamado.profesional)}`
      : '';
    frases.push(`Favor ingresar a la ${llamado.sala}${profesional}.`);
  } else if (llamado.profesional) {
    frases.push(`Favor presentarse con el profesional ${paraLeer(llamado.profesional)}.`);
  }

  const emitir = () => {
    frases.forEach((texto, i) => {
      const mensaje = new SpeechSynthesisUtterance(texto);
      mensaje.lang = voz?.lang || 'es-AR';
      if (voz) mensaje.voice = voz;

      // Con energía: el nombre va más fuerte y agudo; la indicación, más pausada.
      mensaje.volume = 1;
      mensaje.pitch = i === 0 ? 1.25 : 1.1;
      mensaje.rate = i === 0 ? 1.0 : 0.95;

      sintetizador.speak(mensaje);
    });
  };

  // cancel() vacía la cola de forma asíncrona. Si se encola en el mismo tick,
  // Chrome descarta el primer utterance y se pierde el nombre del paciente:
  // solo se escucha "Favor ingresar a la sala...". Por eso se espera un tick,
  // y solo se cancela si realmente había algo sonando.
  if (sintetizador.speaking || sintetizador.pending) {
    sintetizador.cancel();
    setTimeout(emitir, 180);

    return;
  }

  emitir();
}

export function WaitingRoom() {
  const [currentCall, setCurrentCall] = useState(null);
  const [history, setHistory] = useState([]);
  const [connected, setConnected] = useState(false);
  // Cuántos llamados llegaron en vivo desde que la pantalla está abierta.
  // Sirve de `key` del bloque del anuncio: al cambiar, React lo vuelve a
  // montar y el desvanecido corre de nuevo.
  const [llamado, setLlamado] = useState(0);
  const [audioListo, setAudioListo] = useState(false);

  // Los navegadores bloquean audio y voz hasta que hay una interacción del
  // usuario. Es una política del navegador, no algo que se pueda evitar: por
  // eso la pantalla pide un único clic al abrirse y después no molesta más.
  const desbloquearAudio = () => {
    // getVoices() devuelve vacío hasta que se lo llama al menos una vez;
    // conviene disparar la carga acá para que el primer anuncio ya la tenga.
    window.speechSynthesis?.getVoices?.();

    playChime();
    anunciarPorVoz({ paciente: 'Sistema de turnos activado' });
    setAudioListo(true);
  };

  // Recupera el estado al recargar: el WebSocket solo entrega eventos nuevos.
  useEffect(() => {
    api.get('/publico/llamados')
      .then((res) => {
        // La API responde con un Resource: los llamados vienen dentro de `data`.
        const list = Array.isArray(res.data) ? res.data : (res.data.data ?? []);
        if (list.length > 0) {
          setCurrentCall(list[0]);
          setHistory(list.slice(1));
        }
      })
      .catch((err) => console.error('No se pudo cargar el historial de llamados', err));
  }, []);

  useEffect(() => {
    // El canal y el alias del evento deben coincidir con PacienteLlamado.php
    const channel = echo.channel('turnos-publico');

    channel.listen('.paciente.llamado', (e) => {
      setCurrentCall(e);
      // Solo avanza con el evento en vivo, nunca al cargar: una pantalla que
      // parpadea al encenderla entrena a ignorarla. Es contador y no número
      // de turno porque rellamar al mismo paciente debe anunciarse igual.
      setLlamado((n) => n + 1);
      // La tabla muestra 8 filas: entran sin scroll en una pantalla de TV.
      setHistory((prev) => [e, ...prev].slice(0, 8));

      playChime();
      // La voz entra después del tono para que no se pisen.
      setTimeout(() => anunciarPorVoz(e), 900);
    });

    const pusher = echo.connector?.pusher;
    const syncState = ({ current }) => setConnected(current === 'connected');

    if (pusher) {
      pusher.connection.bind('state_change', syncState);
      // Lectura inicial fuera del cuerpo del efecto, para no encadenar renders.
      queueMicrotask(() => setConnected(pusher.connection.state === 'connected'));
    }

    return () => {
      pusher?.connection.unbind('state_change', syncState);
      echo.leaveChannel('turnos-publico');
    };
  }, []);

  // El historial que llega por WebSocket ya incluye al llamado actual, pero el
  // que se carga al recargar la página no. Se unifica acá para que la tabla no
  // muestre al mismo paciente dos veces.
  const llamados = currentCall
    ? [currentCall, ...history.filter((h) => h.appointment_id !== currentCall.appointment_id)]
    : history;

  /*
    Alto exacto de la pantalla: en un televisor colgado nadie desliza, así que
    lo que se pase del alto queda invisible para siempre. El relleno, la
    separación y la tipografía se achican en pantallas de poco alto, y los
    `clamp` miran `min(vw, vh)` para responder al lado más chico.

    Por debajo de `lg` el layout se apila y ahí sí se permite scroll, porque
    recortar sería peor.
  */
  return (
    <div className="flex h-screen flex-col overflow-y-auto bg-slate-950 p-4 text-white lg:overflow-hidden 2xl:p-6">
      <header className="relative mb-3 2xl:mb-5">
        {/* Único indicador que queda: un punto de estado para que el personal
            sepa si la pantalla sigue recibiendo llamados. No es un control. */}
        <span
          role="status"
          aria-label={connected ? 'Conectado en tiempo real' : 'Sin conexión'}
          title={connected ? 'Conectado en tiempo real' : 'Sin conexión'}
          className={`absolute top-2 right-2 size-2 rounded-full ${connected ? 'bg-emerald-600/60' : 'bg-red-500'}`}
        />

        {/* Franja de aviso: lo primero que mira el paciente al entrar. */}
        <div className="rounded-xl bg-teal-700 py-3 text-center">
          <h1 className="text-3xl font-semibold tracking-wide text-white uppercase">
            Favor atienda la pantalla
          </h1>
        </div>
      </header>

      {/* Único paso manual de toda la pantalla: los navegadores no dejan sonar
          audio sin una interacción previa. Se resuelve con un clic al abrirla y
          después desaparece, así queda solo de visualización. */}
      {!audioListo && (
        <button
          type="button"
          onClick={desbloquearAudio}
          className="fixed inset-0 z-50 flex cursor-pointer flex-col items-center justify-center gap-3 bg-slate-950/95 text-center"
        >
          <span className="text-3xl font-semibold text-white uppercase">
            Tocá para activar el sonido
          </span>
          <span className="text-lg text-slate-400 uppercase">
            Se hace una sola vez al encender la pantalla.
          </span>
        </button>
      )}

      {/* 60/40 a favor del último llamado: es el dato que la persona necesita
          ahora, y la lista de la derecha solo sirve para reubicarse si se
          perdió el anuncio. Estaba al revés (5fr/7fr, o sea 42/58). */}
      <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-[3fr_2fr] 2xl:gap-5">

        {/* Último llamado: los tres datos apilados, cada uno con su etiqueta.
            Es lo que el paciente tiene que poder leer desde el fondo de la sala. */}
        <section className="flex min-h-0 flex-col rounded-xl border border-slate-800 bg-slate-900 p-4 2xl:p-6">
          <div className="mb-3 flex items-center justify-between border-b border-slate-800 pb-3 2xl:mb-5">
            <h2 className="text-sm font-medium tracking-wider text-slate-400 uppercase">
              Último paciente llamado
            </h2>
            {currentCall && (
              /* Parte del anuncio, no una etiqueta de interfaz. */
              <span className="tabular rounded-md bg-teal-700 px-3.5 py-1.5 text-2xl font-semibold uppercase">
                Turno {currentCall.turno}
              </span>
            )}
          </div>

          {currentCall ? (
            /*
              Un desvanecido corto y nada más: sin desplazamiento ni escala.
              Se mira de lejos y durante horas, así que todo lo que se mueva de
              más cansa antes que en una pantalla de trabajo, y el anuncio ya
              tiene tono y voz para llamar la atención.
            */
            <div key={llamado} className="entra flex flex-1 flex-col justify-center gap-4 2xl:gap-7">
              <div>
                <p className="text-base font-medium tracking-[0.15em] text-slate-400 uppercase">
                  Paciente
                </p>
                {/* Escala con la pantalla: la misma se cuelga en salas
                    distintas y se mira desde distancias distintas. El clamp
                    pone piso y techo; `break-words`, porque un nombre
                    compuesto no entra en una línea a este tamaño. */}
                <p className="mt-1 text-[clamp(2.75rem,min(8vw,10vh),7.5rem)] leading-[1.05] font-bold break-words text-white uppercase">
                  {currentCall.paciente}
                </p>
              </div>

              <div>
                <p className="text-base font-medium tracking-[0.15em] text-slate-400 uppercase">
                  Consultorio
                </p>
                {/* Al mismo tamaño que el nombre: son los dos datos que la
                    persona necesita, quién y adónde. */}
                <p className="tabular mt-1 text-[clamp(2.75rem,min(8vw,10vh),7.5rem)] leading-[1.05] font-bold break-words text-amber-400 uppercase">
                  {currentCall.sala ?? 'Sin asignar'}
                </p>
              </div>

              <div>
                <p className="text-base font-medium tracking-[0.15em] text-slate-400 uppercase">
                  Profesional
                </p>
                {/* Queda por debajo a propósito: es dato de contexto, no lo
                    que hay que hacer. */}
                <p className="mt-1 text-[clamp(1.75rem,min(4.5vw,5.5vh),4rem)] leading-tight font-bold break-words text-emerald-400 uppercase">
                  {currentCall.profesional ?? 'Sin asignar'}
                </p>
              </div>
            </div>
          ) : (
            <p className="flex flex-1 items-center justify-center text-2xl text-slate-600">
              Esperando llamadas
            </p>
          )}
        </section>

        {/* Tabla de llamados: permite al paciente ubicarse aunque haya perdido
            el anuncio de voz. */}
        <section className="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
          <table className="w-full table-fixed">
            <caption className="sr-only">Pacientes llamados recientemente</caption>
            <thead>
              <tr className="border-b border-slate-800 bg-slate-800/60">
                <th scope="col" className="w-[44%] px-5 py-3 text-left text-sm font-medium tracking-wider text-slate-300 uppercase">
                  Paciente
                </th>
                <th scope="col" className="w-[20%] px-3 py-3 text-center text-sm font-medium tracking-wider text-slate-300 uppercase">
                  Consultorio
                </th>
                <th scope="col" className="w-[36%] px-5 py-3 text-left text-sm font-medium tracking-wider text-slate-300 uppercase">
                  Profesional
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {llamados.length === 0 ? (
                <tr>
                  <td colSpan={3} className="px-5 py-10 text-center text-lg text-slate-600">
                    Sin llamados todavía.
                  </td>
                </tr>
              ) : (
                llamados.map((item, index) => {
                  // El primero es el llamado en curso: se destaca para que
                  // coincida con lo que muestra el panel de la izquierda.
                  const enCurso = index === 0;

                  return (
                    <tr
                      key={`${item.appointment_id}-${index}`}
                      className={enCurso ? 'bg-teal-900/40' : undefined}
                    >
                      <td className={`truncate px-5 py-4 text-2xl uppercase ${enCurso ? 'font-bold text-white' : 'font-semibold text-slate-300'}`}>
                        {item.paciente}
                      </td>
                      <td className={`tabular px-3 py-4 text-center text-2xl uppercase ${enCurso ? 'font-bold text-amber-400' : 'font-semibold text-slate-400'}`}>
                        {item.sala ?? '—'}
                      </td>
                      <td className={`truncate px-5 py-4 text-xl uppercase ${enCurso ? 'font-bold text-emerald-400' : 'font-medium text-slate-400'}`}>
                        {item.profesional ?? '—'}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </section>

      </div>
    </div>
  );
}
