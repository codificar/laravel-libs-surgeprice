<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
        <meta charset="utf-8">
        <title>Surge Fare Setup</title>
    </head>
    <body>
        <h3>Configurações gerais</h3>

        <form action="{{ route('surgeprice.save_settings') }}" method="POST">
        <label>Caminho no sistema para modelos de ML:</label><br/>
        <input type="text" name="model_files_path"  value="{{$settings->model_files_path}}"></br><br/>
        <label>Janela de atualização para surge factor: </label>
        <input type="text" style="text-align:center;" maxlength="2" size="2" name="update_surge_window" value="{{$settings->update_surge_window}}"> minutos</br><br/>
        <label>Delimitadores para surge factor: </label>
        <input type="text" style="text-align:center;" maxlength="3" size="3" name="min_surge"  value="{{$settings->min_surge}}">
        <label> até </label>
        <input type="text" style="text-align:center;" maxlength="3" size="3" name="max_surge"  value="{{$settings->max_surge}}"></br><br/>
        <label for="cars">Método delimitador para surge factor</label><br/>
        <select name="delimiter" onchange="setPreview(this.value)">
        @foreach($delimiters as $delimiter => $val)
            <option value="{{$delimiter}}" @if ($delimiter == $settings->delimiter) selected="selected" @endif >{{$delimiter}}</option>
        @endforeach
        </select><br/>
        <img id="preview" height="200px" src="{{ $delimiters[$settings->delimiter] }}">

        <h3>Configurações avançadas (Machine Learning)</h3>
        <details>
        <summary><i>Local Outlier Factor</i></summary>
        <p/>
        <label>N.º neighbors: </label>
        <input type="text" style="text-align:center;" maxlength="2" size="2" name="lof_neighbors"  value="{{$settings->lof_neighbors}}"></br>
        <label>Fator de contaminação: </label>
        <input type="text" style="text-align:center;" maxlength="4" size="4" name="lof_contamination"  value="{{$settings->lof_contamination}}"></br>
        </details>

        <h3>
        <button>Salvar</button>
        </h3>
        </form>
        <hr>
        
        <h3>Regiões</h3>
        
        <p>
        @if(count($regions) > 0)
        @foreach($regions as $region)
        <div>
        <form action="{{ route('surgeprice.manage_region') }}" method="POST">
        <input name="id" type="hidden" value="{{$region->id}}">
        <input name="cities" id="{{$region->id}}-cities" type="hidden" value="">
        <label><b>{{$region->country}}</b></label>
        <span>  |  </span>
        <label>  Estado: </label>
        <select name="state">
            <option value="-">-</option>
        @foreach($states as $state)
            <option value="{{$state}}" @if ($state == $region->state) selected="selected" @endif >{{$state}}</option>
        @endforeach
        </select>
        <span>  |  </span>
        <button type="button" @if(!$region->id)disabled @endif onclick="toggleCities('c{{$region->id}}', this)">&#9658; Cidades</button>
        <span>  |  </span>
        <label>Tamanho das surge areas: </label>
        <select name="area_size">
            <option value="-">-</option>
        @foreach($area_sizes as $size)
            <option value="{{$size}}" @if ($size == $region->area_size) selected="selected" @endif >{{$size}}</option>
        @endforeach
        </select>
        <span>  |  </span>
        <label>total atual: <b>{{$region->total_areas}}</b></label>
        <span>  |  </span>
        <label>Mínimo de requests por área: </label>
        <input type="text" style="text-align:center;" maxlength="3" size="3" name="min_area_requests"  value="{{$region->min_area_requests}}">
        <span>  |  </span>
        <button type="submit" name="mode" value="save">Salvar</button>
        <span>  |  </span>
        <button type="submit" @if(!$region->id)disabled @endif name="mode" value="delete" onclick="return confirm('Excluir região {{$region->state}}?')">Excluir</button>
        </form>
        </div>
        @if($region->id)
        <div id="c{{$region->id}}" style="display: none;">
            <p>
            @if(count($region->cities))
                @foreach($region->cities as $i => $city)
                    @if((intval($i))%5==0 && intval($i) > 0)
                    <br/>
                    @endif    
                    <span>
                        <input type="checkbox" @if($city['enabled'])checked @endif onclick="updateCity(this, {{$region->id}})" id="{{ $city['id'] }}" name="{{ $city['name'] }}">
                        <label>{{ $city['name'] }}</label>
                        | 
                    </span>
                @endforeach
            @else
                Ainda não existem cidades para esta região.
            @endif
            </p>
        </div>
        @endif
        @endforeach
        @else
        <i>Não existem regiões cadastradas.</i>
        @endif
        </p>
        <button id="createRegion">Adicionar</button>


        <script>
            @if($response_message)
            alert("{{$response_message}}");
            @endif
            document.getElementById('createRegion').addEventListener('click', createRegion);

            function createRegion()
            {
                window.location = "{{route('surgeprice.create_region')}}";
            }

            function updateCity(city, regionId)
            {
                try
                {
                    all_cities = JSON.parse(document.getElementById(regionId+'-cities').value);
                } catch(e)
                {
                    all_cities = {};
                }
                // // TODO: ver chave para overwrite em vez de push
                all_cities[city.id] = {'id': city.id, 'name': city.name, 'enabled': city.checked};
                document.getElementById(regionId+'-cities').value = JSON.stringify(all_cities);
            }

            function saveCities(regionId)
            {
                // var x = document.getElementById(regionId);
                // all_cities = []
                // for (index = 0; index < x.children[0].children.length; index++) {
                //     input = x.children[0].children[index].children[0];
                //     if(input)
                //     {
                //         // TODO: mudar para onCheck e chamar updateCity (vira overwrite)
                //         all_cities.push({'id': input.id, 'name': input.name, 'enabled': input.checked});
                //     }
                // }
                // document.getElementById(regionId+'-cities').value = JSON.stringify(all_cities);
            }

            function toggleCities(regionId, bt)
            {
                // saveCities(regionId)
                // console.log(document.getElementById('cities').value);
                var x = document.getElementById(regionId);
                if (x.style.display === "none")
                {
                    bt.innerHTML = "&#9660; Cidades";
                    x.style.display = "block";
                }
                else
                {
                    bt.innerHTML = "&#9658; Cidades";
                    x.style.display = "none";
                }
            }

            function setPreview(p)
            {
                switch (p) {
                    @foreach($delimiters as $delimiter => $val)case "{{$delimiter}}":
                        document.getElementById('preview').src = "{{$val}}";
                        break;
                    @endforeach default:
                        break;
                }
            }
        </script>
        </body>
</html>