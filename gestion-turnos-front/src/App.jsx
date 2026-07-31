import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { useAuth } from './context/authContextValue';
import { Login } from './pages/Login';
import { Dashboard } from './pages/Dashboard';
import { Reception } from './pages/Reception';
import { Triage } from './pages/Triage';
import { DoctorPanel } from './pages/DoctorPanel';
import { WaitingRoom } from './pages/WaitingRoom';

/**
 * La sesión se comprueba contra el backend, no leyendo localStorage.
 * Mientras se resuelve no se decide nada: redirigir antes haría que un
 * usuario con sesión válida termine en el login cada vez que recarga.
 */
function ProtectedRoute({ children }) {
  const { user, cargando } = useAuth();

  if (cargando) {
    return (
      <div style={{ padding: '40px', textAlign: 'center', fontFamily: 'sans-serif', color: '#64748b' }}>
        Verificando sesión...
      </div>
    );
  }

  return user ? children : <Navigate to="/login" replace />;
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          {/* Rutas Públicas */}
          <Route path="/login" element={<Login />} />
          <Route path="/tv" element={<WaitingRoom />} />

          {/* Rutas Protegidas */}
          <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
          <Route path="/reception" element={<ProtectedRoute><Reception /></ProtectedRoute>} />
          <Route path="/triage" element={<ProtectedRoute><Triage /></ProtectedRoute>} />
          <Route path="/doctor" element={<ProtectedRoute><DoctorPanel /></ProtectedRoute>} />

          {/* Ruta por defecto */}
          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
