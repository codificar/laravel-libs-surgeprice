<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
        <meta charset="utf-8">
        <title>{{$title}}</title>
        <style>
        #map {
            height: 100%;
        }
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        </style>
    </head>
    <body>
        <div id="map"></div>
        <script>
            var map;
            // document.getElementById('inner').innerHTML = JSON.stringify(cont['results'][0]['geometry']['bounds']);
            // @for ($i = 0; $i < 2; $i++)
            // @endfor

            function getColor()
            {
                return '#'+(Math.random()*0xFFFFFF<<0).toString(16);
            }

            function initMap() {
                map = new google.maps.Map(document.getElementById('map'), {
                zoom: 13,
                center: {lat: -1.37, lng: -48.4}
                });

                // @foreach($areas as $i => $area)

                // var area = {!! $area !!};
                // var areaCoords = [
                //     { lat: area.coordinates[0][0][1], lng: area.coordinates[0][0][0] },
                //     { lat: area.coordinates[0][1][1], lng: area.coordinates[0][1][0] },
                //     { lat: area.coordinates[0][2][1], lng: area.coordinates[0][2][0] },
                //     { lat: area.coordinates[0][3][1], lng: area.coordinates[0][3][0] },
                //     { lat: area.coordinates[0][4][1], lng: area.coordinates[0][4][0] },
                // ];

                // // new google.maps.Marker({
                // //     position: areaCoords[1],
                // //     label: '{{ $prefixes[$i] }}',
                // //     map: map,
                // // });

                // var myColor = getColor();

                // var surgeArea = new google.maps.Polygon({
                //     paths: areaCoords,
                //     strokeColor: myColor,
                //     strokeOpacity: 0.8,
                //     strokeWeight: 2,
                //     fillColor: myColor,
                //     fillOpacity: 0.35,
                // });

                // surgeArea.setMap(map);
                // @endforeach

                var pinColor = "#005500";
                var pinLabel = "A";

                // Pick your pin (hole or no hole)
                var pinSVGHole = "M12,11.5A2.5,2.5 0 0,1 9.5,9A2.5,2.5 0 0,1 12,6.5A2.5,2.5 0 0,1 14.5,9A2.5,2.5 0 0,1 12,11.5M12,2A7,7 0 0,0 5,9C5,14.25 12,22 12,22C12,22 19,14.25 19,9A7,7 0 0,0 12,2Z";
                var labelOriginHole = new google.maps.Point(12,15);


                var markerImage = {  // https://developers.google.com/maps/documentation/javascript/reference/marker#MarkerLabel
                    path: pinSVGHole,
                    anchor: new google.maps.Point(12,17),
                    fillOpacity: 1,
                    fillColor: pinColor,
                    strokeWeight: 1,
                    strokeColor: "white",
                    scale: 2,
                    labelOrigin: labelOriginHole
                };

                @foreach($pts as $i => $pt)
                markerImage.fillColor = "{{$colors[$i]}}";
                var pt = {!! $pt !!};
                new google.maps.Marker({
                    position:{lat: pt.coordinates[1] , lng: pt.coordinates[0]},
                    title: '{{$ids[$i]}}',
                    map: map,
                    icon: markerImage,
                });
                @endforeach

                // Define the LatLng coordinates for the polygon's path.
                // const areaCoords = [
                //     { lat: -1.3831437, lng: -48.4549912 },
                //     { lat: -1.3192702, lng: -48.4549912 },
                //     { lat: -1.3192702, lng: -48.3713774 },
                //     { lat: -1.3831437, lng: -48.3713774 },
                //     { lat: -1.3831437, lng: -48.4549912 },
                // ];
                // Construct the polygon.
                // const surgeArea = new google.maps.Polygon({
                //     paths: areaCoords,
                //     strokeColor: "#FF0000",
                //     strokeOpacity: 0.8,
                //     strokeWeight: 2,
                //     fillColor: "#FF0000",
                //     fillOpacity: 0.35,
                // });

                // surgeArea.setMap(map);

            }
        </script>
        <script
            src="https://maps.googleapis.com/maps/api/js?key={{$key}}&libraries=geometry&callback=initMap"
            async ></script>
    </body>
</html>