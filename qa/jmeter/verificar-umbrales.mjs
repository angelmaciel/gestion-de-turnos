// Corta el CI si la prueba de carga no cumple los umbrales.
// Uso: node qa/jmeter/verificar-umbrales.mjs <informe>/statistics.json [p95 máx. ms] [% de errores máx.]
import { readFileSync } from 'node:fs';

const [ruta, p95Max = '500', erroresMax = '1'] = process.argv.slice(2);
if (!ruta) {
  console.error('Falta la ruta a statistics.json del informe de JMeter.');
  process.exit(2);
}

const estadisticas = JSON.parse(readFileSync(ruta, 'utf8'));
const filas = Object.values(estadisticas).map((e) => ({
  peticion: e.transaction,
  muestras: e.sampleCount,
  'errores %': Number(e.errorPct.toFixed(2)),
  'mediana ms': Math.round(e.medianResTime),
  'p95 ms': Math.round(e.pct2ResTime),
  'p99 ms': Math.round(e.pct3ResTime),
  'pet./s': Number(e.throughput.toFixed(1)),
}));
console.table(filas);

const total = estadisticas.Total;
const fallas = [];
if (total.pct2ResTime > Number(p95Max)) fallas.push(`P95 total de ${Math.round(total.pct2ResTime)} ms (máximo ${p95Max} ms)`);
if (total.errorPct > Number(erroresMax)) fallas.push(`${total.errorPct.toFixed(2)} % de errores (máximo ${erroresMax} %)`);

if (fallas.length) {
  console.error(`✗ No cumple: ${fallas.join('; ')}`);
  process.exit(1);
}
console.log(`✓ Cumple: P95 ${Math.round(total.pct2ResTime)} ms ≤ ${p95Max} ms y ${total.errorPct.toFixed(2)} % de errores ≤ ${erroresMax} %`);
