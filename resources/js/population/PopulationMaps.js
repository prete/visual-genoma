function createPopulationMaps(){
	var populations = [];
	populations['eas'] = [40.847060, 125.332031];
	populations['sas'] = [24.686952, 82.441406];
	populations['amr'] = [8.581021, -79.628906];
	populations['afr'] = [19.160156, 4.390229];
	populations['eur'] = [49.951220, 13.886719];
	for(var p in populations){
		createMap(p, populations[p]);
	}
}

function createMap(population, c){
	console.log(population, c);
	var width = 200;
	var height = 200;
	
	var svg = d3.select("#map-"+population).append("svg")
	    .attr("width", width)
	    .attr("height", height);
	
	var projection = d3.geo.mercator()
	    .center([40.847060, 125.332031])
	    //.scale(150)
	    .rotate([-180,0]);

	var path = d3.geo.path()
	    .projection(projection);
	
	var g = svg.append("g");
	
	// load and display the World
	d3.json("/resources/js/population/world.json", function(error, topology) {
	    g.selectAll("path")
	      .data(topojson.object(topology, topology.objects.countries)
	          .geometries)
	    .enter()
	      .append("path")
	      .attr("d", path);
	    
	    d3.csv("resources/js/population/"+population+".csv", function(error, data) {
	        g.selectAll("circle")
	           .data(data)
	           .enter()
	           .append("circle")
	           .attr("cx", function(d) {
	                   return projection([d.lon, d.lat])[0];
	           })
	           .attr("cy", function(d) {
	                   return projection([d.lon, d.lat])[1];
	           })
	           .attr("r", 5)
	           .style("fill", "red");
	    });
	});
	
	// zoom and pan
	var zoom = d3.behavior.zoom()
	    .on("zoom",function() {
	        g.attr("transform","translate("+ 
	            d3.event.translate.join(",")+")scale("+d3.event.scale+")");
	        g.selectAll("circle")
	            .attr("d", path.projection(projection));
	        g.selectAll("path")  
	            .attr("d", path.projection(projection)); 
	
	  });
	
	svg.call(zoom)
}