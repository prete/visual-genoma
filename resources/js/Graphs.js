var drawViarantsPerChromosomeBarGraph = function(id) {
  var margin = {top: 40, right: 20, bottom: 30, left: 40};
  var width = $(id).width() - margin.left - margin.right;
  var height = $(id).height() - margin.top - margin.bottom;
        
  var x = d3.scaleOrdinal()
      .range([0, width], .1);
  
  var y = d3.scaleLinear()
      .range([height, 0]);
  
  var xAxis = d3.axisBottom()
      .scale(x);
      //.orient("bottom");
  
  var yAxis = d3.axisLeft()
      .scale(y);
      //.orient("left");
  
  var svg = d3.select(id).append("svg")
      .attr("width", width + margin.left + margin.right)
      .attr("height", height + margin.top + margin.bottom)
    .append("g")
      .attr("transform", "translate(" + margin.left + "," + margin.top + ")");
  
  d3.json("/graphs/source/variants/per-chromosome", function(error, dataset) {
      if (error) throw error;
        
      x.domain(dataset.map(function(d) { return d.chromosome; }));
      y.domain([0, d3.max(dataset, function(d) { return d.variants; })]);
      
      svg.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + height + ")")
        .call(xAxis);
      
      svg.append("g")
        .attr("class", "y axis")
        .call(yAxis)
    
      svg.selectAll(".bar")
        .data(dataset)
      .enter().append("rect")
        .attr("class", "bar")
        .attr("x", function(d) { return x(d.chromosome); })
        .attr("width", x.range())
        .attr("y", function(d) { return y(d.variants); })
        .attr("height", function(d) { return height - y(d.variants); })
        .attr("fill", function(d) {
          var fillColor = [0, (d.variants%255), 0];
  				return "rgb(" + fillColor.join(',') + ")";
  		   });
  });
}



var drawViarantsTypePieGraph = function(id) {
    var diameter = $(id).width();
    var format = d3.format(",d");
    var color = d3.schemeCategory20c;
        
    var bubble = d3.pack()
        //.sort(null)
        .size([diameter, diameter])
        .padding(1.5);
    
    var svg = d3.select(id).append("svg")
        .attr("width", diameter)
        .attr("height", diameter)
        .attr("class", "bubble");
    
    d3.json("/graphs/source/variants/per-type", function(error, dataset) {
      if (error) throw error;
    
      var root = { name: 'Tipos de variantes', children: [] };
      
      for(d in dataset){ 
        root.children.push({ name: dataset[d].variant_type+ ' ('+dataset[d].variants+')', type: dataset[d].variant_type, size: dataset[d].variants });
      }
      
      var node = svg.selectAll(".node")
          .data(bubble.nodes(classes(root))
          .filter(function(d) { return !d.children; }))
        .enter().append("g")
          .attr("class", "node")
          .attr("transform", function(d) { return "translate(" + d.x + "," + d.y + ")"; });
    
      node.append("title")
          .text(function(d) { return d.className + ": " + format(d.value); });
    
      node.append("circle")
          .attr("r", function(d) { return d.r; })
          .style("fill", function(d) { 
            switch(d.variantType) {
              case 'SNV':
                  return d3.rgb(0,255,0);
                  break;
              case 'DEL':
                  return d3.rgb(255,0,0);
                  break;
              case 'INS':
                  return d3.rgb(0,0,255);
                  break;
              case 'MIXED':
                  return d3.rgb(255,255,0);
                  break;                  
              default:
                  return d3.rgb(0,255,255);
            }
          });
    
      node.append("text")
          .attr("dy", ".3em")
          .style("text-anchor", "middle")
          .text(function(d) { return d.className.substring(0, d.r / 3); });
    });
    
    // Returns a flattened hierarchy containing all leaf nodes under the root.
    function classes(root) {
      var classes = [];
    
      function recurse(name, node) {
        if (node.children) node.children.forEach(function(child) { recurse(node.name, child); });
        else classes.push({packageName: name, className: node.name, value: node.size, variantType: node.type});
      }
    
      recurse(null, root);
      return {children: classes};
    }
    
    d3.select(self.frameElement).style("height", diameter + "px");
}



