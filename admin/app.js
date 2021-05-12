if (/[?&]ko(&|=|$)/.test(location.href)) {
	$('<div class="alert alert-danger">Identifiants incorrects</div>').prependTo('body').hide().slideDown();
}
$('[data-graph]').each(function () {
	var $graph = $(this);
	var data = $graph.data('graph');
	var days = data.length;
	$graph.CanvasJSChart({
		title: {
			text: days + " derniers jours"
		},
        animationEnabled: true,
		data: [
			{
				type: 'line',
				dataPoints: $graph.data('graph').map(function (data) {
					return {
						label: data[0].split(/\s/)[0],
						y: data[1]
					};
				})
			}
		]
	});
	/*
	var data = $graph.data('graph');
	var id = 'graph-' + (Math.random() + '').substr(2);
	$graph.attr('id', id);
	var options = {
		seriesDefaults: {
			rendererOptions: {
				smooth: true
			}
		},
		xaxis: {
            renderer: $.jqplot.DateAxisRenderer
        }
	};
	console.log(data);
	var plot1 = $.jqplot(id, [data], options);
	*/
});
