<?php
class SistemaPermisos {
    private $permisos = [];
    
    public function __construct($permisosArray) {
        $this->permisos = $permisosArray;
    }
    
    public function puedeVer($modulo) {
        return isset($this->permisos[$modulo]['ver']) && $this->permisos[$modulo]['ver'];
    }
    
    public function puedeAgregar($modulo) {
        return isset($this->permisos[$modulo]['agregar']) && $this->permisos[$modulo]['agregar'];
    }
    
    public function puedeEditar($modulo) {
        return isset($this->permisos[$modulo]['editar']) && $this->permisos[$modulo]['editar'];
    }
    
    public function puedeEliminar($modulo) {
        return isset($this->permisos[$modulo]['eliminar']) && $this->permisos[$modulo]['eliminar'];
    }
    
    public function puedeCambiarEstado($modulo) {
        return isset($this->permisos[$modulo]['cambiar_estado']) && $this->permisos[$modulo]['cambiar_estado'];
    }
    
    // Método para generar clases CSS según permisos
    public function getClaseBoton($modulo, $accion) {
        $metodo = 'puede' . ucfirst($accion);
        if (method_exists($this, $metodo)) {
            return $this->$metodo($modulo) ? '' : 'btn-disabled';
        }
        return 'btn-disabled';
    }
    
    // Método para verificar si está deshabilitado
    public function estaDeshabilitado($modulo, $accion) {
        $metodo = 'puede' . ucfirst($accion);
        if (method_exists($this, $metodo)) {
            return !$this->$metodo($modulo);
        }
        return true;
    }
}
?>