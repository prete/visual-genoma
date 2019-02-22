function BodyMapCreate(cuerpoConfig, organos, callbackHoverOrganoIn, callbackHoverOrganoOut, callbackClickOrgano, callbackLoad) {
  
    var self = this;
  
    self.cargado = false;
    self.cuerpoConfig = cuerpoConfig;
    self.organos = organos;
    self.nombreOrganos = {};
    self.organosActivos = [];
    //self.callbackHoverOrganoIn = callbackHoverOrganoIn;
    //self.callbackHoverOrganoOut = callbackHoverOrganoOut;
    self.grafico = {};
  
    self.organoSeleccionado = null;
  
    self.snaphelper = Snap("#" + cuerpoConfig.svgId);
  
    var escala = 170;
    var despX = -30;
    var vBoxParam = "" + despX + " 0 " + escala + " " + escala;
    
    self.snaphelper.addClass("bodymapcontenedor");
    
    self.snaphelper.attr({
        viewBox: vBoxParam,
        //width: 600,
        //height: 500
    });

  	self._inicializarOrgano = function(svg, svgPathId) {
        
        var svgElement = svg.select("#" + svgPathId);
        
        if (svgElement) {
            self.organosActivos.push(svgPathId);
            self._recursivelyChangeProperties(svgElement, self.cuerpoConfig.organoConfig.colour, self.cuerpoConfig.organoConfig.opacity);

            svgElement.node.onclick = function(parametro){
                if(callbackClickOrgano)
                    callbackClickOrgano(svgPathId, self._obtenerNombre(svgPathId));
            };
            
            svgElement.hover(
                //IN
                function (parametro) {
                    self._seleccionInterna(svg, svgElement, true);
                    if(callbackHoverOrganoIn)
                        callbackHoverOrganoIn(svgPathId, self._obtenerNombre(svgPathId));
                },
                //OUT
                function (parametro) {
                    self._seleccionInterna(svg, svgElement, false);
                    if(callbackHoverOrganoOut)
                         callbackHoverOrganoOut(svgPathId, self._obtenerNombre(svgPathId));
                }
            );
		} else {
		    //Este elemento no existe en el svg.
		}
  	};

  	self._recursivelyChangeProperties = function(svgElement, colour, opacity) {
        if (svgElement) {
            svgElement.selectAll("*").forEach(
                function(innerElement) {
                self._recursivelyChangeProperties(innerElement);
            });
  
            svgElement.attr({"fill": colour, "fill-opacity": opacity});
        }
    };

    self._seleccionInterna = function(svg, svgElement, bEstaSeleccionado) {
        var svgElements = svg.select(".bodymaporganoseleccionado");
        if (svgElements) {
            if (Array.isArray(svgElements)) {
                for (var i = 0; i < svgElements.length; i++) {
                    svgElements[i].removeClass("bodymaporganoseleccionado");
                }
            } else {
                svgElements.removeClass("bodymaporganoseleccionado");
            }
        }
        self._marcarElemento(svgElement, bEstaSeleccionado);
    };

	self._marcarElemento = function(svgElement, bEstaSeleccionado) {
        if (svgElement) {
            svgElement.selectAll("*").forEach(
                function(innerElement) {
                    self._marcarElemento(innerElement, bEstaSeleccionado);
                });
            
            if (bEstaSeleccionado) {
                svgElement.addClass("bodymaporganoseleccionado");
                self.organoSeleccionado = "" + svgElement.attr("id");
            } else {
                svgElement.removeClass("bodymaporganoseleccionado");
                self.organoSeleccionado = null;
            }
        }
    };

    self.seleccionar = function(svgPathId) {
        if (!self.cargado)
            return;
            
        var svgElement = self.grafico.select("#" + svgPathId);
        self._marcarElemento(svgElement, true);
    };

    self.deseleccionar = function() {
        if (!self.cargado)
            return;
            
        if(self.organoSeleccionado) {
            var svgElement = self.grafico.select("#" + self.organoSeleccionado);
            self._marcarElemento(svgElement, false);
        }
    };

    self.obtenerSeleccionado = function() {
        return self.organoSeleccionado;
    };

    self.obtenerOrganosActivosIds = function() {
        var vector = self.organosActivos;
        var resultado = [];

		for (var i = 0; i < vector.length; i++) {
		    if(vector[i].svgPathId != undefined)
			    resultado.push(vector[i].svgPathId);
			else
			    resultado.push(vector[i]);
		}

		return resultado;
    };

    self.obtenerOrganosIds = function() {
        var vector = self.organos;
        var resultado = [];

		for (var i = 0; i < vector.length; i++) {
		    if(vector[i].svgPathId != undefined)
			    resultado.push(vector[i].svgPathId);
			else
			    resultado.push(vector[i]);
		}

		return resultado;
    };

    self._cargarNombre = function(svgPathId, nombre) {
        if (svgPathId && nombre) {
            self.nombreOrganos[svgPathId] = nombre;
        }
    };
    
    self._obtenerNombre = function(svgPathId) {
        if (self.nombreOrganos[svgPathId])
            return self.nombreOrganos[svgPathId];
        else
            return "";
    };
    
    // Inicializar
  	Snap.load(self.cuerpoConfig.pathSvg, function (svgCuerpo) {
  
  		var vector = self.organos;
  		for (var i = 0; i < vector.length; i++) {
  		    if(vector[i].svgPathId != undefined) {
  			    self._inicializarOrgano(svgCuerpo, vector[i].svgPathId);
  			    self._cargarNombre(vector[i].svgPathId, vector[i].name);
  			} else {
  			    self._inicializarOrgano(svgCuerpo, vector[i]);
  			}
  		}
  
  		self.grafico = svgCuerpo.select("g");
  		self.snaphelper.append(self.grafico);
  		self.grafico.drag();
  
  		if (callbackLoad)
  		    callbackLoad();
  		    
        self.cargado = true;
  	});

    return self;
}
