<?php
return [


  // Auth
  'login'           => 'Auth/LoginController@index',     // Formulario
  'login/acceder'   => 'Auth/LoginController@acceder',   // POST credenciales
  'logout'          => 'Auth/LoginController@salir',     // Cerrar sesión
  'recuperar'       => 'Auth/LoginController@recuperar',     // Cerrar sesión
  'reestablecer'    => 'Auth/LoginController@reestablecer',     // Cerrar sesión
  /* Dashboard principal */
  'intranet'        => 'Intranet/HomeController@index',

  /* … resto de rutas … */
  'set-contexto'    => 'Auth/ContextoController@set',

  //CONTROL DE SESION PARA EL CONTEXTO
  'sigi/rol/cambiarSesion'             => 'Sigi/RolController@cambiarSesion',
  'sedes/cambiarSesion'            => 'Sigi/SedesController@cambiarSesion',
  'sigi/periodoAcademico/cambiarSesion'         => 'Sigi/PeriodoAcademicoController@cambiarSesion',


  //SIGI/ PERIODOS ACADEMICOS
  'sigi/periodoAcademico'                 => 'Sigi/PeriodoAcademicoController@index',
  'sigi/periodoAcademico/data'            => 'Sigi/PeriodoAcademicoController@data',
  'sigi/periodoAcademico/nuevo'           => 'Sigi/PeriodoAcademicoController@nuevo',
  'sigi/periodoAcademico/guardar'         => 'Sigi/PeriodoAcademicoController@guardar',
  'sigi/periodoAcademico/editar/{id}'     => 'Sigi/PeriodoAcademicoController@editar',

  /* Módulo SIGI (ejemplo) */
  'sigi'            => 'Sigi/HomeController@index',
  // SIGI/DOCENTES
  'sigi/docentes'                    => 'Sigi/DocentesController@index',
  'sigi/docentes/nuevo'              => 'Sigi/DocentesController@create',
  'sigi/docentes/guardar'            => 'Sigi/DocentesController@guardar',
  'sigi/docentes/ver/{id}'           => 'Sigi/DocentesController@ver',
  'sigi/docentes/editar/{id}'        => 'Sigi/DocentesController@editar',
  'sigi/docentes/actualizar/{id}'    => 'Sigi/DocentesController@actualizar',
  // Endpoint Ajax para DataTables
  'sigi/docentes/data'               => 'Sigi/DocentesController@data',
  //SIGI/PERMISOS
  'sigi/docentes/permisos/{id}'        => 'Sigi/DocentesController@permisos',

  // SIGI/DATOSINSTITUCIONALES
  'sigi/datosInstitucionales'              => 'Sigi/DatosInstitucionales@index',
  'sigi/datosInstitucionales/guardar'      => 'Sigi/DatosInstitucionales@guardar',

  // SIGI/DATOSSISTEMA
  'sigi/datosSistema'              => 'Sigi/DatosSistema@index',
  'sigi/datosSistema/guardar'      => 'Sigi/DatosSistema@guardar',

  // SIGI/SEDES
  'sigi/sedes'                => 'Sigi/SedesController@index',
  'sigi/sedes/nuevo'          => 'Sigi/SedesController@nuevo',
  'sigi/sedes/guardar'        => 'Sigi/SedesController@guardar',
  'sigi/sedes/editar/{id}'    => 'Sigi/SedesController@editar',
  // Endpoint Ajax para DataTables
  'sigi/sedes/data' => 'Sigi/SedesController@data',
  //SIGI/ SEDES / PROGRAMAS
  'sigi/sedes/programas/{id}'        => 'Sigi/SedesController@programas',
  'sigi/sedes/programasGuardar/{id}' => 'Sigi/SedesController@programasGuardar',


  // SIGI/PROGRAMAS DE ESTUDIO
  'sigi/programas'                => 'Sigi/ProgramasController@index',
  'sigi/programas/nuevo'          => 'Sigi/ProgramasController@nuevo',
  'sigi/programas/guardar'        => 'Sigi/ProgramasController@guardar',
  'sigi/programas/editar/{id}'    => 'Sigi/ProgramasController@editar',
  // DataTable AJAX (si lo usas)
  'sigi/programas/data'           => 'Sigi/ProgramasController@data',

  // SIGI/PLANES DE ESTUDIO
  'sigi/planes'                => 'Sigi/PlanesController@index',
  'sigi/planes/nuevo'          => 'Sigi/PlanesController@nuevo',
  'sigi/planes/guardar'        => 'Sigi/PlanesController@guardar',
  'sigi/planes/editar/{id}'    => 'Sigi/PlanesController@editar',
  // DataTable AJAX
  'sigi/planes/data'           => 'Sigi/PlanesController@data',
  // Endpoints AJAX para selects dependientes:
  'sigi/planes/porPrograma/{id_programa}'         => 'Sigi/PlanesController@porPrograma',

  // SIGI/MODULO FORMATIVO
  'sigi/moduloFormativo'             => 'Sigi/ModuloFormativoController@index',
  'sigi/moduloFormativo/nuevo'       => 'Sigi/ModuloFormativoController@nuevo',
  'sigi/moduloFormativo/guardar'     => 'Sigi/ModuloFormativoController@guardar',
  'sigi/moduloFormativo/editar/{id}' => 'Sigi/ModuloFormativoController@editar',
  // DataTable AJAX
  'sigi/moduloFormativo/data'        => 'Sigi/ModuloFormativoController@data',
  // Endpoints AJAX para selects dependientes:
  'sigi/moduloFormativo/porPlan/{id_plan}' => 'Sigi/ModuloFormativoController@porPlan',

  // SIGI/SEMESTRE
  'sigi/semestre'              => 'Sigi/SemestreController@index',
  'sigi/semestre/nuevo'        => 'Sigi/SemestreController@nuevo',
  'sigi/semestre/guardar'      => 'Sigi/SemestreController@guardar',
  'sigi/semestre/editar/{id}'  => 'Sigi/SemestreController@editar',
  // DataTable AJAX
  'sigi/semestre/data'         => 'Sigi/SemestreController@data',
  // Endpoints AJAX para selects dependientes:
  'sigi/semestre/porModulo/{id_modulo}' => 'Sigi/SemestreController@porModulo',
  'sigi/semestre/porPlan/{id_plan}' => 'Sigi/SemestreController@porPlan',
  'sigi/semestre/porPrograma/{id_programa}' => 'Sigi/SemestreController@porPrograma',

  // SIGI/UNIDAD DIDACTICA
  'sigi/unidadDidactica'               => 'Sigi/UnidadDidacticaController@index',
  'sigi/unidadDidactica/nuevo'         => 'Sigi/UnidadDidacticaController@nuevo',
  'sigi/unidadDidactica/guardar'       => 'Sigi/UnidadDidacticaController@guardar',
  'sigi/unidadDidactica/editar/{id}'   => 'Sigi/UnidadDidacticaController@editar',
  // DataTable AJAX
  'sigi/unidadDidactica/data'          => 'Sigi/UnidadDidacticaController@data',
  // Endpoints dependientes:
  'sigi/unidadDidactica/porSemestre/{id_semestre}'    => 'Sigi/UnidadDidacticaController@porSemestre',

  // SIGI/COMPETENCIAS
  'sigi/competencias'                   => 'Sigi/CompetenciasController@index',
  'sigi/competencias/nuevo'             => 'Sigi/CompetenciasController@nuevo',
  'sigi/competencias/guardar'           => 'Sigi/CompetenciasController@guardar',
  'sigi/competencias/editar/{id}'       => 'Sigi/CompetenciasController@editar',
  'sigi/competencias/data'              => 'Sigi/CompetenciasController@data',
  // Endpoints dependientes:
  'sigi/competencias/porModulo/{id_modulo}'    => 'Sigi/CompetenciasController@porModulo',

  // SIGI/INDICADOR DE LOGRO DE COMPETENCIAS
  'sigi/indicadorLogroCompetencia/index/{id_competencia}'       => 'Sigi/IndicadorLogroCompetenciaController@index',
  'sigi/indicadorLogroCompetencia/nuevo/{id_competencia}' => 'Sigi/IndicadorLogroCompetenciaController@nuevo',
  'sigi/indicadorLogroCompetencia/guardar'                => 'Sigi/IndicadorLogroCompetenciaController@guardar',
  'sigi/indicadorLogroCompetencia/editar/{id}'            => 'Sigi/IndicadorLogroCompetenciaController@editar',
  'sigi/indicadorLogroCompetencia/eliminar/{id}/{id_comp}' => 'Sigi/IndicadorLogroCompetenciaController@eliminar',
  'sigi/indicadorLogroCompetencia/data/{id_competencia}'  => 'Sigi/IndicadorLogroCompetenciaController@data',

  // SIGI/CAPACIDADES
  'sigi/capacidades'                        => 'Sigi/CapacidadesController@index',
  'sigi/capacidades/nuevo'                  => 'Sigi/CapacidadesController@nuevo',
  'sigi/capacidades/guardar'                => 'Sigi/CapacidadesController@guardar',
  'sigi/capacidades/editar/{id}'            => 'Sigi/CapacidadesController@editar',
  'sigi/capacidades/data'                   => 'Sigi/CapacidadesController@data',
  // Endpoints dependientes:

  // SIGIINDICADOR DE LOGRO DE CAPACIDAD
  'sigi/indicadorLogroCapacidad/index/{id_capacidad}'      => 'Sigi/IndicadorLogroCapacidadController@index',
  'sigi/indicadorLogroCapacidad/nuevo/{id_capacidad}' => 'Sigi/IndicadorLogroCapacidadController@nuevo',
  'sigi/indicadorLogroCapacidad/guardar'            => 'Sigi/IndicadorLogroCapacidadController@guardar',
  'sigi/indicadorLogroCapacidad/editar/{id}'        => 'Sigi/IndicadorLogroCapacidadController@editar',
  'sigi/indicadorLogroCapacidad/eliminar/{id}/{id_cap}' => 'Sigi/IndicadorLogroCapacidadController@eliminar',
  'sigi/indicadorLogroCapacidad/data/{id_capacidad}' => 'Sigi/IndicadorLogroCapacidadController@data',

  //SIGI/LOGS
  'sigi/logs'                        => 'Sigi/LogsController@logs',
  'sigi/logs/data'    => 'Sigi/LogsController@data',

  // SIGO/SISTEMAS INTREGRADOS
  'sigi/sistemasIntegrados'      => 'Sigi/SistemasIntegradosController@index',
  'sigi/sistemasIntegrados/data' => 'Sigi/SistemasIntegradosController@data',



  // ------------------------------  RUTAS ACADEMICO-------------------------------------------->>

  // ACADEMICO/ESTUDIANTES
  'academico/estudiantes'             => 'Academico/EstudiantesController@index',
  'academico/estudiantes/data'        => 'Academico/EstudiantesController@data',
  'academico/estudiantes/nuevo'           => 'Academico/EstudiantesController@nuevo',
  'academico/estudiantes/guardar'         => 'Academico/EstudiantesController@guardar',
  'academico/estudiantes/editar/{id}'     => 'Academico/EstudiantesController@editar',


  // ACADEMICO/PROGRAMACION DE UNIDADES DIDACTICAS
  'academico/programacionUnidadDidactica'                        => 'Academico/ProgramacionUnidadDidacticaController@index',
  'academico/programacionUnidadDidactica/nuevo'                  => 'Academico/ProgramacionUnidadDidacticaController@nuevo',
  'academico/programacionUnidadDidactica/editar/{id}' => 'Academico/ProgramacionUnidadDidacticaController@editar',
  'academico/programacionUnidadDidactica/guardarEdicion' => 'Academico/ProgramacionUnidadDidacticaController@guardarEdicion',
  'academico/programacionUnidadDidactica/listar'                   => 'Academico/ProgramacionUnidadDidacticaController@listar',


  'academico/silabos/pdf/{id}' => 'Academico/SilabosController@pdf',


  // ACADEMICO/ SESIONES
  'academico/sesiones/ver/{id_programacion}'         => 'Academico/SesionesController@index',
  'academico/sesiones/data/{id_programacion}'    => 'Academico/SesionesController@data',
  'academico/sesiones/editar/{id_sesion}'        => 'Academico/SesionesController@editar',
  'academico/sesiones/guardarEdicionSesion/{id_sesion}' => 'Academico/SesionesController@guardarEdicionSesion',
  'academico/sesiones/duplicar/{id}' => 'Academico/SesionesController@duplicar',
  'academico/sesiones/eliminar/{id}' => 'Academico/SesionesController@eliminar',
  'academico/sesiones/imprimir/{id}' => 'Academico/SesionesController@imprimir',
  'academico/sesiones/pdf/{id}' => 'Academico/SesionesController@pdf',

  //ACADEMICO / MATRICULA
  'academico/matricula/nuevo'    => 'Academico/MatriculaController@nuevo',
  'academico/matricula/agregarUd/{id_matricula}'    => 'Academico/MatriculaController@agregarUd',

  // ACADEMICO / LICENCIAS
  'academico/licencias'                         => 'Academico/LicenciasController@index',
  'academico/licencias/guardar'                 => 'Academico/LicenciasController@guardar',
  'academico/licencias/eliminar/{id}'           => 'Academico/LicenciasController@eliminar',
  'academico/licencias/buscarMatriculaAjax'     => 'Academico/LicenciasController@buscarMatriculaAjax',

  // ACADEMICO / ASISTENCIA
  'academico/asistencia/ver/{id_programacion_ud}'  => 'Academico/AsistenciaController@ver',

  //ACADEMICO / CALIFICACIONES
  '/academico/calificaciones/ver/{id_prog_ud}'  => 'Academico/CalificacionesController@evaluar',
  '/academico/calificaciones/actualizarPonderado'  => 'Academico/CalificacionesController@actualizarPonderado',
  '/academico/calificaciones/agregarCriterio'  => 'Academico/CalificacionesController@agregarCriterio',
  '/academico/calificaciones/getCriterios'  => 'Academico/CalificacionesController@getCriterios',
  '/academico/calificaciones/actualizarCriterios'  => 'Academico/CalificacionesController@actualizarCriterios',








];
